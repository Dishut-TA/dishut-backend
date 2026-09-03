import requests
import json

overpass_url = "http://overpass-api.de/api/interpreter"
# Query boundary=administrative, admin_level=8 within a bbox roughly covering Gunung Halu
query = """
[out:json][timeout:25];
(
  relation["admin_level"="8"](-7.1, 107.25, -6.9, 107.45);
);
out geom;
"""
print("Fetching from Overpass...")
try:
    response = requests.post(overpass_url, data={'data': query})
    data = response.json()
    elements = data.get('elements', [])
    print(f"Found {len(elements)} villages.")
    for el in elements:
        tags = el.get('tags', {})
        print(tags.get('name', 'Unknown'), tags.get('is_in:municipality', ''))
except Exception as e:
    print(f"Error: {e}")
