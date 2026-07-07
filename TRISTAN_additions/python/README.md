# Batch — existing collection, no prompts:
python3 ird_normalize.py stf.csv stf.json --config stf.yml --og-audience-nid 42

# Interactive — new collection, save config for next time:
python3 ird_normalize.py newcoll.csv out.json --save-config newcoll.yml

# Show config template and exit:
python3 ird_normalize.py --help-config

# Files
- dv_solutions_config.yml: Example config file
- dv_solutions.json: Example json file; highlights schema
- ird_normalize.py: The parser
- migrate_plus.*: Files highlighting subsequent drupal migration of content