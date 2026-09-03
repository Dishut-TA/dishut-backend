import requests
import json

overpass_url = "http://overpass-api.de/api/interpreter"
query = """
[out:json][timeout:25];
area["name"="Gunung Halu"]["admin_level"="7"]->.searchArea;
(
  relation["admin_level"="8"](area.searchArea);
);
out geom;
"""
print("Fetching from Overpass...")
try:
    response = requests.post(overpass_url, data={'data': query})
    data = response.json()
    elements = data.get('elements', [])
    print(f"Found {len(elements)} villages in Gunung Halu.")
    for el in elements:
        print(el.get('tags', {}).get('name', 'Unknown'))
except Exception as e:
    print(f"Error: {e}")
