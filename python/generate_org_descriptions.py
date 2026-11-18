"""
generate_org_descriptions.py

Reads an Excel file of organizations, scrapes each org's website for "about" content, uses Gemini to generate a short description (falling
back to Gemini + Google Search grounding when there's no website or no usable scraped text), and writes the results to a new Excel file.

Install dependencies:
    pip install pandas openpyxl requests beautifulsoup4 google-genai pydantic lxml

Set your API key before running:
    export GEMINI_API_KEY="your-key-here"        (macOS/Linux)
    setx GEMINI_API_KEY "your-key-here"           (Windows)
"""

import os
import re
import time
import logging
from concurrent.futures import ThreadPoolExecutor

import pandas as pd
import requests
import urllib3
from bs4 import BeautifulSoup
from pydantic import BaseModel

from google import genai
from google.genai.types import GenerateContentConfig, GoogleSearch, Tool

logging.basicConfig(level=logging.INFO, format="%(asctime)s  %(levelname)s  %(message)s")
log = logging.getLogger(__name__)
logging.getLogger("google_genai.models").setLevel(logging.WARNING)

# We deliberately retry with verify=False as a last resort for sites with
# broken/expired certs, so silence the resulting warning rather than spam it.
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

REQUEST_HEADERS = {
    "User-Agent": ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36"),
    "Accept-Language": "en-US,en;q=0.9",
}


# ============================================================
# CONFIG — edit these to match your setup
# ============================================================

ROOT_FOLDER = os.path.dirname(os.path.abspath(__file__))     # folder holding the input file
INPUT_FILENAME = "ASTI_with_UN_regions.csv"                  # your source Excel file
OUTPUT_FILENAME = "ASTI_with_descriptions.xlsx"              # where results get written
SHEET_NAME = 0                                               # sheet index or name

# Column names as they appear in the source file
COL_NAME = "official_name"
COL_ACRONYM = "official_acronym"
COL_WEBSITE = "website"
COL_COUNTRY = "country"
COL_REGION = "region"
COL_SUBREGION = "subregion"
COL_AGENCY_TYPE = "agencytype"
SUBJECT_COLUMNS = ["Crops", "Fisheries", "Forestry", "Livestock",
                    "Natural_resources", "Socio_economics", "Other"]

MODEL_NAME = "gemini-2.5-flash"                 # check https://ai.google.dev/gemini-api/docs/models for current options
REQUEST_TIMEOUT = 10                            # seconds, for scraping HTTP requests
MIN_TEXT_LENGTH = 200                           # below this many characters, treat scrape as "no usable text"
BATCH_SIZE = 200                                # process this many orgs, then ask whether to continue
MAX_CONCURRENT_WORKERS = 5                      # requests running at once within a block -- raise cautiously,
                                                # this is what actually controls Gemini rate-limit risk
STAGGER_SECONDS = 0.3                           # tiny delay between submitting each request, to avoid a burst

ABOUT_PATH_HINTS = ["about", "about-us", "a-propos", "quienes-somos", "who-we-are", "mission"]


# ============================================================
# SCRAPING HELPERS
# ============================================================

def clean_url(raw_url: str) -> str | None:
    """Normalize a URL cell: strip whitespace, take the first URL if multiple are space-separated, add a scheme if missing."""
    if not isinstance(raw_url, str) or not raw_url.strip():
        return None
    url = raw_url.strip().split()[0]
    if not url.startswith(("http://", "https://")):
        url = "https://" + url
    return url


def url_variants(url: str) -> list[str]:
    """A handful of plausible alternates for a URL: swap http/https, toggle the www. prefix. Order matters -- most likely first."""
    variants = [url]

    if url.startswith("https://"):
        variants.append("http://" + url[len("https://"):])
    elif url.startswith("http://"):
        variants.append("https://" + url[len("http://"):])

    for v in list(variants):
        if "://www." in v:
            variants.append(v.replace("://www.", "://", 1))
        else:
            variants.append(v.replace("://", "://www.", 1))

    seen, ordered = set(), []
    for v in variants:
        if v not in seen:
            seen.add(v)
            ordered.append(v)
    return ordered


def _get(url: str, verify: bool = True) -> requests.Response:
    return requests.get(url, timeout=REQUEST_TIMEOUT, headers=REQUEST_HEADERS, verify=verify)


def fetch_page_text(url: str) -> tuple[str, BeautifulSoup] | None:
    """Fetch a page and return visible text, or None on failure. Tries a few URL variants and, as a last resort, 
    skips SSL verification for sites with broken/expired certificates."""
    last_error = None
    for candidate in url_variants(url):
        try:
            resp = _get(candidate)
            resp.raise_for_status()
        except requests.exceptions.SSLError:
            try:
                resp = _get(candidate, verify=False)
                resp.raise_for_status()
            except requests.RequestException as e:
                last_error = e
                continue
        except requests.RequestException as e:
            last_error = e
            continue

        soup = BeautifulSoup(resp.text, "html.parser")
        for tag in soup(["script", "style", "nav", "footer"]):
            tag.decompose()
        text = re.sub(r"\s+", " ", soup.get_text(separator=" ")).strip()
        return text, soup

    log.info(f"  fetch failed for {url}: {last_error}")
    return None


def find_about_link(soup: BeautifulSoup, base_url: str, org_name: str = "", org_acronym: str = "") -> str | None:
    """Look for links matching the org itself, prioritizing org-specific pages over parent org content."""
    candidates = []
    
    for a in soup.find_all("a", href=True):
        href = a["href"].lower()
        label = a.get_text().strip().lower()
        
        # Exact name/acronym match gets highest priority
        if org_acronym and org_acronym.lower() in href + label:
            candidates.append((a["href"], 10))
        elif org_name and org_name.lower() in href + label:
            candidates.append((a["href"], 9))
        
        # Research-specific pages (labs, divisions, units)
        research_hints = ABOUT_PATH_HINTS + ["research", "laboratory", "division", "unit", "team", "lab"]
        if any(hint in href or hint in label for hint in research_hints):
            candidates.append((a["href"], 5))
    
    if candidates:
        href_full = max(candidates, key=lambda x: x[1])[0]
        if href_full.startswith("http"):
            return href_full
        return base_url.rstrip("/") + "/" + href_full.lstrip("/")
    return None


def scraped_text_is_relevant(text: str, org_name: str, org_acronym: str) -> bool:
    """Check if scraped text actually mentions this organization (not just its parent).
    
    Returns True only if:
    1. The text mentions the org name or acronym, AND
    2. The text doesn't appear to be primarily about a parent organization
    """
    text_lower = text.lower()
    # Handle missing/null values
    if not isinstance(org_name, str):
        org_name = ""
    if not isinstance(org_acronym, str):
        org_acronym = ""
    name_lower = org_name.lower()
    acronym_lower = org_acronym.lower()
    
    # Must mention org name or acronym somewhere
    if not (name_lower in text_lower or (acronym_lower and acronym_lower in text_lower)):
        return False
    
    # Check if text is primarily about a parent organization
    # Count mentions of common parent org keywords
    parent_keywords = ["university", "ministry", "department of", "faculty of", "school of", 
                        "institute of", "college of", "center of"]
    parent_mention_count = sum(text_lower.count(kw) for kw in parent_keywords)
    
    # If parent org keywords appear many times (>5) and org name appears only once or twice,
    # it's likely parent org content
    org_mention_count = text_lower.count(name_lower) + (text_lower.count(acronym_lower) if acronym_lower else 0)
    
    # If parent keywords dominate (appear 5+ times) and org is barely mentioned (1-2 times),
    # it's probably parent org content
    if parent_mention_count >= 5 and org_mention_count <= 2:
        return False
    
    return True


def scrape_org_text(url: str, org_name: str = "", org_acronym: str = "") -> str | None:
    """Get homepage text plus about-page text (if findable). Returns None if nothing usable was retrieved."""
    homepage = fetch_page_text(url)
    if homepage is None:
        return None
    text, soup = homepage

    about_url = find_about_link(soup, url, org_name, org_acronym)
    if about_url and about_url != url:
        about = fetch_page_text(about_url)
        if about:
            text += " " + about[0]

    text = text[:6000]  # cap length to control token usage
    if len(text) < MIN_TEXT_LENGTH:
        return None
    
    # Check if scraped text is actually about this organization
    if org_name or org_acronym:
        if not scraped_text_is_relevant(text, org_name, org_acronym):
            log.info(f"  scraped text does not mention {org_name or org_acronym}, treating as no usable text")
            return None
    
    return text


# ============================================================
# GEMINI API SECTION
# ============================================================

client = genai.Client(api_key=os.environ.get("GEMINI_API_KEY"))


def _generate_with_retry(max_retries: int = 4, **kwargs):
    """Call Gemini, retrying with exponential backoff on rate-limit errors. Concurrency makes 429s much more likely than the old one-at-a-time
    version, so this is what keeps a burst of requests from just failing."""
    delay = 2
    for attempt in range(max_retries):
        try:
            return client.models.generate_content(**kwargs)
        except Exception as e:
            msg = str(e)
            if "429" in msg or "RESOURCE_EXHAUSTED" in msg or "rate" in msg.lower():
                if attempt == max_retries - 1:
                    raise
                log.info(f"  rate limited, retrying in {delay}s...")
                time.sleep(delay)
                delay *= 2
                continue
            raise
    raise RuntimeError("unreachable")


class OrgDescription(BaseModel):
    description: str
    confidence: str   # "high" or "low"
    reason: str = ""  # reason for low confidence (empty if high confidence)


def is_research_unit(org_name: str, agency_type: str) -> bool:
    """Detect if org is a research unit/lab that likely doesn't have a standalone website."""
    keywords = ["laboratory", "research", "division", "unit", "center", "institute", "lab"]
    # Handle missing/null values
    if not isinstance(org_name, str):
        org_name = ""
    if not isinstance(agency_type, str):
        agency_type = ""
    org_lower = org_name.lower()
    type_lower = agency_type.lower()
    return any(k in org_lower or k in type_lower for k in keywords)


def is_generic_faculty_only(org_name: str) -> bool:
    """Detect if org name is ONLY generic faculty/school keywords with no specific identifier.
    These are too generic to scrape meaningfully (e.g., 'Faculty of Agriculture' appears in many countries).
    Skip scraping for these and go straight to search-grounding.
    
    Examples of generic (True): "Faculty of Agriculture", "Escuela de Veterinaria", "Department of Research"
    Examples of specific (False): "Faculty of Agricultural Engineering", "Escuela de Veterinaria INTA", 
                                    "National Institute of Agricultural Research"
    """
    if not isinstance(org_name, str):
        return False
    
    org_lower = org_name.lower().strip()
    
    # List of generic keywords that make an org name generic
    generic_keywords = ["faculty", "facultad", "faculté", "escuela", "school", 
                        "department", "département", "division"]
    
    # Check if the name starts with a generic keyword
    starts_with_generic = any(org_lower.startswith(kw) for kw in generic_keywords)
    
    if not starts_with_generic:
        return False  # Not generic if it doesn't start with a generic keyword
    
    # If it starts with a generic keyword, check if there's meaningful specificity after it
    # Remove the generic keyword and see what's left
    remaining = org_lower
    for keyword in generic_keywords:
        if remaining.startswith(keyword):
            remaining = remaining[len(keyword):].strip()
            break
    
    # Remove common filler words (English and Romance languages)
    filler_words = ["of", "the", "de", "la", "le", "del", "y", "and", "et", "los", "las", "el"]
    remaining_words = [w for w in remaining.split() if len(w) > 2 and w not in filler_words]
    
    # If 2+ meaningful words remain, it has specificity (e.g., "Agricultural Engineering", "Ciencias Agropecuarias")
    # If fewer, it's just generic (e.g., "Agriculture" or "Veterinaria")
    # Special case: "Ciencias Agropecuarias" (2 words) is still generic, so use < 3 for Spanish/French
    if any(w in remaining for w in ["ciencias", "sciences", "sciences"]):
        return len(remaining_words) < 3
    return len(remaining_words) < 2


def build_org_context(row: pd.Series) -> str:
    """Short plain-text summary of what we already know about the org, used as grounding context in both Gemini paths."""
    subjects = [c for c in SUBJECT_COLUMNS if str(row.get(c, "")).strip().upper() == "YES"]
    parts = [
        f"Name: {row.get(COL_NAME, '')}",
        f"Acronym: {row.get(COL_ACRONYM, '')}",
        f"Country: {row.get(COL_COUNTRY, '')}",
        f"Type: {row.get(COL_AGENCY_TYPE, '')}",
    ]
    if subjects:
        parts.append(f"Subject areas: {', '.join(subjects)}")
    return " | ".join(str(p) for p in parts)


def generate_from_scraped_text(org_context: str, scraped_text: str, org_name: str = "") -> OrgDescription:
    """Path A: summarize the org's own website content. Confidence is the model's own assessment of how clearly the text supports the description
    -- not a blanket "high" just because a scrape succeeded."""
    prompt = (
        "You are describing an agricultural science and technology organization for a public data portal. Based only on the context and website text below, "
        "write a 2-3 sentence factual description of what the organization does. Do not invent details not supported by the text.\n\n"
        "IMPORTANT: The website text may be from a parent organization's website (e.g., a ministry or university). "
        "If the text is primarily about a parent organization and does NOT specifically describe the target organization, "
        "set confidence to 'low' and reason to 'Parent organization content' instead.\n\n"
        "Then set confidence to 'high' if the text clearly and specifically describes this organization's mission and activities, or 'low' if the text is thin, "
        "generic, mostly navigation/boilerplate, about a parent organization, or you are not confident the description is accurate.\n\n"
        "If confidence is 'low', provide a brief reason (e.g., 'Parent organization content', 'Text too generic', 'Insufficient detail', 'Mostly navigation').\n\n"
        f"Organization context: {org_context}\n\n"
        f"Website text: {scraped_text}"
    )
    response = _generate_with_retry(
        model=MODEL_NAME,
        contents=prompt,
        config=GenerateContentConfig(
            response_mime_type="application/json",
            response_schema=OrgDescription,
        ),
    )
    result: OrgDescription = response.parsed
    if result.confidence not in ("high", "low"):
        result.confidence = "low"
        if not result.reason:
            result.reason = "Uncertain confidence"
    return result


def generate_with_search_grounding(org_context: str, org_name: str = "") -> OrgDescription:
    """Path B: no website or no usable scraped text. Ask Gemini to look the organization up via Google Search grounding. Confidence reflects
    whether the model actually found corroborated info, not just that this was the fallback path -- a well-known org found via search can still be
    high confidence.

    Structured output (response_schema) isn't reliably supported together with tools on every model version, so confidence is requested as a
    plain-text marker line and parsed out below instead of via schema.
    """
    # Check if this is a generic faculty name
    is_generic = is_generic_faculty_only(org_name)
    
    if is_generic:
        # For generic faculty names, be explicit about the limitation
        prompt = (
            "Search for this agricultural science and technology organization and write a 2-3 sentence factual description of what it does. "
            "NOTE: This organization has a generic name (e.g., 'Faculty of Agriculture') that appears in multiple countries and institutions. "
            "If you find multiple organizations with this name, describe the most prominent one but note the ambiguity. "
            "If you cannot find a specific, well-documented organization with this exact name, say so plainly.\n\n"
            f"Organization context: {org_context}\n\n"
            "After the description, on its own final line, write exactly 'CONFIDENCE: high' if your search found clear, corroborated information "
            "about this specific organization, or 'CONFIDENCE: low' if the name is too generic, you found multiple organizations, or could not verify it.\n\n"
            "If confidence is 'low', also provide a brief reason on the next line (e.g., 'Generic name - multiple organizations found', 'Could not find specific organization', 'Ambiguous name')."
        )
    else:
        prompt = (
            "Search for this agricultural science and technology organization and write a 2-3 sentence factual description of what it does. If you cannot "
            "find reliable information, say so plainly rather than guessing.\n\n"
            f"Organization context: {org_context}\n\n"
            "After the description, on its own final line, write exactly 'CONFIDENCE: high' if your search found clear, corroborated information "
            "about this specific organization, or 'CONFIDENCE: low' if you could not verify it and had to rely on a general guess.\n\n"
            "If confidence is 'low', also provide a brief reason on the next line (e.g., 'Could not find organization', 'Insufficient information', 'Ambiguous results')."
        )
    
    grounding_tool = Tool(google_search=GoogleSearch())
    response = _generate_with_retry(
        model=MODEL_NAME,
        contents=prompt,
        config=GenerateContentConfig(tools=[grounding_tool]),
    )
    raw = response.text.strip()
    match = re.search(r"CONFIDENCE:\s*(high|low)", raw, re.IGNORECASE)
    confidence = match.group(1).lower() if match else "low"
    description = re.sub(r"CONFIDENCE:\s*(high|low)\s*$", "", raw, flags=re.IGNORECASE).strip()
    
    # Extract reason if confidence is low
    reason = ""
    if confidence == "low":
        # Try to extract reason from the response
        lines = raw.split('\n')
        for i, line in enumerate(lines):
            if 'CONFIDENCE:' in line and i + 1 < len(lines):
                reason = lines[i + 1].strip()
                break
        if not reason:
            reason = "Could not find reliable information"
    
    return OrgDescription(description=description, confidence=confidence, reason=reason)


# ============================================================
# MAIN PIPELINE
# ============================================================

def process_row(row: pd.Series) -> dict:
    org_name = row.get(COL_NAME, "")
    org_acronym = row.get(COL_ACRONYM, "")
    agency_type = row.get(COL_AGENCY_TYPE, "")
    org_context = build_org_context(row)
    url = clean_url(row.get(COL_WEBSITE))

    scraped_text = None
    # Skip scraping for:
    # 1. Research units (labs, divisions) that likely don't have standalone websites
    # 2. Generic faculty names (e.g., "Faculty of Agriculture") that are too generic to describe specifically
    skip_scraping = is_research_unit(org_name, agency_type) or is_generic_faculty_only(org_name)
    
    if url and not skip_scraping:
        try:
            scraped_text = scrape_org_text(url, org_name, org_acronym)
        except Exception as e:
            log.info(f"  scrape error for {url}: {e}")

    try:
        if scraped_text:
            result = generate_from_scraped_text(org_context, scraped_text, org_name)
            source_type = "scraped"
        else:
            result = generate_with_search_grounding(org_context, org_name)
            source_type = "search_grounded"
    except Exception as e:
        log.warning(f"  Gemini call failed for {org_name}: {e}")
        result = OrgDescription(description="", confidence="low", reason="Gemini API error")
        source_type = "failed"

    return {
        "description": result.description,
        "confidence": result.confidence,
        "source_type": source_type,
        "needs_review": result.confidence == "low",
        "low_confidence_reason": result.reason if result.confidence == "low" else "",
    }


def process_block_concurrently(batch: pd.DataFrame, total: int) -> list:
    """Process one block of rows across a small pool of worker threads. Order of results always matches the order of rows in `batch`,
    regardless of which request finishes first."""

    def _worker(item):
        i, row = item
        name = row.get(COL_NAME, f"row {i}")
        log.info(f"  -> [{i + 1}/{total}] {name}")
        result = process_row(row)
        log.info(f"  <- [{i + 1}/{total}] done ({result['source_type']}, confidence={result['confidence']})")
        return result

    items = list(batch.iterrows())

    with ThreadPoolExecutor(max_workers=MAX_CONCURRENT_WORKERS) as executor:
        futures = []
        for item in items:
            futures.append(executor.submit(_worker, item))
            time.sleep(STAGGER_SECONDS)
        return [f.result() for f in futures]


def save_results(df: pd.DataFrame, results: list, output_path: str) -> pd.DataFrame:
    """Write whatever's been processed so far to the output file."""
    out_df = pd.concat(
        [df.iloc[:len(results)].reset_index(drop=True), pd.DataFrame(results)],
        axis=1,
    )
    out_df.to_excel(output_path, index=False)
    return out_df


RESULT_COLUMNS = ["description", "confidence", "source_type", "needs_review", "low_confidence_reason"]


def load_existing_progress(output_path: str) -> list:
    """If an output file already exists from a previous run, load its results so we can resume instead of starting over. Returns an empty
    list if there's no output file yet, or it doesn't look valid."""
    if not os.path.exists(output_path):
        return []

    try:
        existing = pd.read_excel(output_path)
    except Exception as e:
        log.warning(f"Couldn't read existing output file ({e}); starting fresh.")
        return []

    if not all(c in existing.columns for c in RESULT_COLUMNS):
        log.warning("Output file exists but doesn't look like a valid progress file; starting fresh.")
        return []

    results = existing[RESULT_COLUMNS].to_dict("records")
    log.info(f"Resuming: found {len(results)} previously processed organizations in {output_path}")
    return results


def ask_to_continue(done: int, total: int, next_batch: int) -> bool:
    """Prompt the user in the terminal. Returns True to keep going."""
    while True:
        answer = input(
            f"\nProcessed {done}/{total} organizations. "
            f"Continue to the next {next_batch}? (y/n): "
        ).strip().lower()
        if answer in ("y", "yes"):
            return True
        if answer in ("n", "no"):
            return False
        print("Please type 'y' or 'n'.")


def main():
    input_path = os.path.join(ROOT_FOLDER, INPUT_FILENAME)
    output_path = os.path.join(ROOT_FOLDER, OUTPUT_FILENAME)

    log.info(f"Reading {input_path}")
    df = pd.read_csv(input_path, encoding="cp1252")

    total = len(df)
    results = load_existing_progress(output_path)
    already_done = len(results)

    if already_done >= total:
        log.info(f"All {total} organizations are already processed in {output_path}. Nothing to do.")
        return

    try:
        for start in range(already_done, total, BATCH_SIZE):
            end = min(start + BATCH_SIZE, total)
            batch = df.iloc[start:end]

            log.info(f"Processing rows {start + 1}-{end} of {total} "
                        f"({MAX_CONCURRENT_WORKERS} concurrent workers)...")
            results.extend(process_block_concurrently(batch, total))

            save_results(df, results, output_path)
            log.info(f"  saved progress: {len(results)}/{total} rows -> {output_path}")

            if end >= total:
                break  # that was the last block, nothing left to ask about

            next_block_size = min(BATCH_SIZE, total - end)
            if not ask_to_continue(len(results), total, next_block_size):
                log.info(f"Stopped by user. Results for {len(results)} organizations saved to {output_path}")
                return
    except KeyboardInterrupt:
        # Note: results from the block currently in flight are not included --
        # the thread pool waits for already-running requests to finish before
        # this runs, but nothing gets saved until a full block completes.
        save_results(df, results, output_path)
        log.info(f"\nInterrupted. Results for {len(results)} organizations saved to {output_path}")
        log.info("Run the script again to resume from here.")
        return

    log.info(f"Done. Wrote {len(results)} rows to {output_path}")


if __name__ == "__main__":
    main()
