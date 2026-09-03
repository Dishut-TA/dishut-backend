import json

resources = [
    ('1. KabKota', 'kabkotas', {'code': '3201', 'name': 'Kabupaten Bogor', 'latitude': -6.59444, 'longitude': 106.78917}),
    ('2. Kecamatan', 'kecamatans', {'city_id': 1, 'code': '320101', 'name': 'Cibinong', 'latitude': -6.48278, 'longitude': 106.84889}),
    ('3. Desa', 'desas', {'district_id': 1, 'code': '32010101', 'name': 'Ciriung', 'latitude': -6.48278, 'longitude': 106.84889}),
    ('4. Lahan', 'lahans', {'village_id': 1, 'code': 'LHN-001', 'name': 'Lahan Kritis Ciriung Area 1', 'area': 1500.50, 'status': 'active'}),
    ('5. Bibit', 'bibits', {'code': 'S-MAH-001', 'name': 'Mahoni', 'type': 'Kayu', 'category': 'Kehutanan', 'certification_status': 'Bersertifikat', 'status': 'active'}),
    ('6. Jenis Intervensi', 'jenis-intervensis', {'code': 'INT-01', 'name': 'Reboisasi Pola Agroforestri', 'status': 'active'}),
    ('7. Rekomendasi Intervensi', 'rekomendasi-intervensis', {'intervention_type_id': 1, 'name': 'Penanaman Mahoni Jarak 3x3', 'status': 'active'}),
    ('8. KTH', 'kths', {'name': 'KTH Tani Subur', 'coordinator': 'Bapak Anton', 'member_count': 40, 'cdk': 'CDK Wilayah Bogor'}),
    ('9. Donor', 'donors', {'user_id': None, 'donor_name': 'PT. CSR Hebat', 'address': 'Gedung Cyber, Jakarta'}),
    ('10. Seed Specification', 'seed-specifications', {'seed_id': 1, 'min_height': 30, 'max_height': 60, 'stock': 10000, 'price': 12500}),
    ('11. Donation Program', 'donation-programs', {'analysis_result_id': None, 'kth_id': 1, 'seed_specification_id': 1, 'name': 'Program Hijaukan Bogor', 'location': 'Lahan Kritis Ciriung Area 1', 'total_seeds_collected': 0, 'total_seeds_realized': 0, 'status': 'Active'}),
    ('12. Donation', 'donations', {'donation_program_id': 1, 'donor_id': 1, 'seed_id': 1, 'seed_quantity': 200, 'seed_status': 'Pending'}),
    ('13. Transaction', 'transactions', {'donation_id': 1, 'donor_id': 1, 'amount': 2500000, 'transaction_date': '2026-07-17 15:30:00', 'payment_method': 'Mandiri VA', 'status': 'Success'}),
    ('14. Planted Seed', 'planted-seeds', {'donation_program_id': 1, 'seed_id': 1, 'planted_quantity': 100, 'proof_path': 'uploads/planting/bukti_tanam_1.jpg'}),
    ('15. Report', 'reports', {'donation_program_id': 1, 'user_id': 1, 'start_date': '2026-07-01', 'end_date': '2026-07-31', 'status': 'Success', 'report_file_path': 'uploads/reports/laporan_penanaman.pdf'})
]

collection = {
    'info': {
        'name': 'SIGAP JABAR - Full API Test (Modul 1 & Modul 2)',
        'schema': 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
    },
    'item': [],
    'variable': [
        {
            'key': 'baseUrl',
            'value': 'https://master.service.unikom.my.id/api',
            'type': 'string'
        }
    ]
}

headers = [
    {'key': 'Accept', 'value': 'application/json', 'type': 'text'},
    {'key': 'Content-Type', 'value': 'application/json', 'type': 'text'}
]

for name, path, body in resources:
    real_name = name.split(". ")[1]
    folder = {
        'name': name,
        'item': [
            {
                'name': f'1. Create {real_name}',
                'request': {
                    'method': 'POST',
                    'header': headers,
                    'body': {'mode': 'raw', 'raw': json.dumps(body, indent=4)},
                    'url': {'raw': f'{{{{baseUrl}}}}/{path}', 'host': ['{{baseUrl}}'], 'path': [path]}
                }
            },
            {
                'name': f'2. Get All {real_name}',
                'request': {
                    'method': 'GET',
                    'header': [{'key': 'Accept', 'value': 'application/json', 'type': 'text'}],
                    'url': {'raw': f'{{{{baseUrl}}}}/{path}', 'host': ['{{baseUrl}}'], 'path': [path]}
                }
            },
            {
                'name': f'3. Update {real_name}',
                'request': {
                    'method': 'PUT',
                    'header': headers,
                    'body': {'mode': 'raw', 'raw': json.dumps(body, indent=4)},
                    'url': {'raw': f'{{{{baseUrl}}}}/{path}/1', 'host': ['{{baseUrl}}'], 'path': [path, '1']}
                }
            },
            {
                'name': f'4. Delete {real_name}',
                'request': {
                    'method': 'DELETE',
                    'header': [{'key': 'Accept', 'value': 'application/json', 'type': 'text'}],
                    'url': {'raw': f'{{{{baseUrl}}}}/{path}/1', 'host': ['{{baseUrl}}'], 'path': [path, '1']}
                }
            }
        ]
    }
    collection['item'].append(folder)

with open('postman_collection.json', 'w') as f:
    json.dump(collection, f, indent=2)
print('Collection saved to postman_collection.json')
