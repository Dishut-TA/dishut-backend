<?php

$file = 'c:\\laragon\\www\\dishut-web-admin-main\\dishut-web-admin-main\\src\\pages\\StaffPDAS\\PelaksanaanDanMonitoring\\PenugasanPenyuluh\\components\\CreatePenugasanModal.tsx';
$content = file_get_contents($file);

// Replace fetchTargetData for 'pelaksanaan'
$search1 = <<<'EOD'
        } else if (kategori === 'pelaksanaan') {
          const token = localStorage.getItem('token');
          // const res = await fetch(`${API_URL}/donation-programs`, {
          //   headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
          // });
          const res = await fetch(`${API_URL}/donation-programs`, {
            headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
          });
          const json = await res.json();
          const list = json.data || json.payload || [];
          
          setTargetOptions(list.map((item: any) => ({
            value: item.id.toString(),
            label: `[Program] ${item.nama_program}`
          })));
        }
EOD;

$replace1 = <<<'EOD'
        } else if (kategori === 'pelaksanaan') {
          const token = localStorage.getItem('token');
          const res = await fetch(`${API_URL}/penugasan`, {
            headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
          });
          const json = await res.json();
          const list = json.data || [];
          
          const pelaksanaanList = list.filter((item: any) => item.jenisKegiatan === 'Pelaksanaan Penanaman' && item.status === 'Menunggu Penugasan');

          setTargetOptions(pelaksanaanList.map((item: any) => ({
            value: JSON.stringify({ id: item.id, type: item.source_type }),
            label: `[Program] ${item.program} - ${item.lokasi}`
          })));
        }
EOD;

$content = str_replace($search1, $replace1, $content);

// Replace handleSubmit POST request
$search2 = <<<'EOD'
      if (kategori === 'pelaksanaan') {
        const payload = {
          penyuluh_id: parseInt(penyuluh),
          tanggal_mulai: tanggal,
          batas_waktu: periode,
          arahan: catatan
        };
        const res = await fetch(`${API_URL}/pelaksanaan/${lokasiAtauProgram}/penugasan`, {
          method: 'POST', headers, body: JSON.stringify(payload)
        });
        if (!res.ok) throw new Error("Gagal menyimpan penugasan pelaksanaan");
      } else {
        const payloadValidasi = {
          zone_id: parseInt(lokasiAtauProgram),
          penyuluh_id: parseInt(penyuluh),
          tanggal_mulai: tanggal,
          batas_waktu: periode,
          arahan: catatan
        };
        const res = await fetch(`${API_URL}/field-validations/assign`, {
          method: 'POST', headers, body: JSON.stringify(payloadValidasi)
        });
        if (!res.ok) throw new Error("Gagal menyimpan penugasan validasi");
      }

      toast.success('Penugasan berhasil dibuat!', { id: loadingId });
EOD;

$replace2 = <<<'EOD'
      let payload: any = {
        penyuluh_id: parseInt(penyuluh),
        tanggal_mulai: tanggal,
        batas_waktu: periode,
        arahan: catatan
      };

      if (kategori === 'pelaksanaan') {
        const sourceData = JSON.parse(lokasiAtauProgram);
        payload.source_id = sourceData.id;
        payload.source_type = sourceData.type;
        payload.jenis_kegiatan = 'Pelaksanaan Penanaman';
      } else {
        payload.source_id = parseInt(lokasiAtauProgram);
        payload.source_type = 'App\\Models\\AnalysisResultZone';
        payload.jenis_kegiatan = 'Validasi Lokasi';
      }

      const res = await fetch(`${API_URL}/penugasan/assign`, {
        method: 'POST', headers, body: JSON.stringify(payload)
      });
      
      if (!res.ok) throw new Error("Gagal menyimpan penugasan");

      toast.success('Penugasan berhasil dibuat!', { id: loadingId });
EOD;

$content = str_replace($search2, $replace2, $content);

file_put_contents($file, $content);
echo "File patched successfully.";
