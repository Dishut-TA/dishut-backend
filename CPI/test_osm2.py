import requests
import json

overpass_url = "http://overpass-api.de/api/interpreter"
query = """
[out:json][timeout:25];
relation["name"~"Gunung Halu", i]["admin_level"="7"];
out tags;
"""
print("Fetching from Overpass...")
try:
    response = requests.post(overpass_url, data={'data': query})
    print(response.text)
except Exception as e:
    print(f"Error: {e}")
