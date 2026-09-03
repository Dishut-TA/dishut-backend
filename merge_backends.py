import os
import shutil
import re

source_dir = 'dishut-service-users-main'
dest_dir = '.'

# 1. Copy Models
models_src = os.path.join(source_dir, 'app/Models')
models_dest = os.path.join(dest_dir, 'app/Models')
for item in os.listdir(models_src):
    src_path = os.path.join(models_src, item)
    dest_path = os.path.join(models_dest, item)
    if os.path.isfile(src_path):
        shutil.copy2(src_path, dest_path)

# 2. Copy Controllers
controllers_src = os.path.join(source_dir, 'app/Http/Controllers/Api')
controllers_dest = os.path.join(dest_dir, 'app/Http/Controllers/Api')
os.makedirs(controllers_dest, exist_ok=True)
for item in os.listdir(controllers_src):
    src_path = os.path.join(controllers_src, item)
    dest_path = os.path.join(controllers_dest, item)
    if os.path.isfile(src_path):
        shutil.copy2(src_path, dest_path)

# 3. Copy Migrations
migrations_src = os.path.join(source_dir, 'database/migrations')
migrations_dest = os.path.join(dest_dir, 'database/migrations')
for item in os.listdir(migrations_src):
    src_path = os.path.join(migrations_src, item)
    dest_path = os.path.join(migrations_dest, item)
    if os.path.isfile(src_path):
        shutil.copy2(src_path, dest_path)

# 4. Merge Seeders
seeders_src = os.path.join(source_dir, 'database/seeders')
seeders_dest = os.path.join(dest_dir, 'database/seeders')
for item in os.listdir(seeders_src):
    if item == 'DatabaseSeeder.php':
        continue
    src_path = os.path.join(seeders_src, item)
    dest_path = os.path.join(seeders_dest, item)
    if os.path.isfile(src_path):
        shutil.copy2(src_path, dest_path)

# Merge DatabaseSeeder.php
dinhut_seeder_path = os.path.join(dest_dir, 'database/seeders/DatabaseSeeder.php')
users_seeder_path = os.path.join(source_dir, 'database/seeders/DatabaseSeeder.php')

with open(users_seeder_path, 'r', encoding='utf-8') as f:
    users_seeder_content = f.read()

# The DINHUT seeder has:
# $this->call([
#     UserSeeder::class,
#     CitySeeder::class,
#     DistrictSeeder::class,
#     VillageSeeder::class,
#     LandSeeder::class,
#     SeedSeeder::class,
#     InterventionTypeSeeder::class,
#     InterventionRecommendationSeeder::class,
# ]);
# We will inject these at the end of the run() method in users_seeder_content
dinhut_calls = """
        // Merged from DINHUT
        $this->call([
            CitySeeder::class,
            DistrictSeeder::class,
            VillageSeeder::class,
            LandSeeder::class,
            SeedSeeder::class,
            InterventionTypeSeeder::class,
            InterventionRecommendationSeeder::class,
        ]);
"""
# Replace the last `    }` inside `public function run(): void` with `dinhut_calls + '    }'`
# We'll just replace the closing brace of the run method. 
# Looking at the file, the run method ends just before the class closing brace `}`.
users_seeder_content = users_seeder_content.replace('        }\n    }\n}', '        }\n' + dinhut_calls + '    }\n}')

with open(dinhut_seeder_path, 'w', encoding='utf-8') as f:
    f.write(users_seeder_content)

# 5. Merge routes/api.php
dinhut_routes_path = os.path.join(dest_dir, 'routes/api.php')
users_routes_path = os.path.join(source_dir, 'routes/api.php')

with open(dinhut_routes_path, 'r', encoding='utf-8') as f:
    dinhut_routes_content = f.read()
    
with open(users_routes_path, 'r', encoding='utf-8') as f:
    users_routes_content = f.read()

# Extract use statements and routes from users
users_use_statements = "\n".join([line for line in users_routes_content.split('\n') if line.startswith('use ') and 'Route' not in line])
users_routes_body = "\n".join([line for line in users_routes_content.split('\n') if not line.startswith('use ') and not line.startswith('<?php')])

# Merge into DINHUT routes
# Insert use statements after <?php
merged_routes_content = dinhut_routes_content.replace('<?php\n', '<?php\n' + users_use_statements + '\n')
# Append users routes to the end
merged_routes_content += "\n// --- Merged from dishut-service-users-main ---\n" + users_routes_body

with open(dinhut_routes_path, 'w', encoding='utf-8') as f:
    f.write(merged_routes_content)

print("Merge completed successfully.")
