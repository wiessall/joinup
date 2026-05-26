# Batch — existing collection, no prompts:
python3 ird_normalize.py stf.csv stf.json --config stf.yml --og-audience-nid 42

# Interactive — new collection, save config for next time:
python3 ird_normalize.py newcoll.csv out.json --save-config newcoll.yml

# Show config template and exit:
python3 ird_normalize.py --help-config
