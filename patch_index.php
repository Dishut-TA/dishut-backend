<?php

$file = 'c:\\laragon\\www\\dishut-web-admin-main\\dishut-web-admin-main\\src\\pages\\StaffPDAS\\PelaksanaanDanMonitoring\\PenugasanPenyuluh\\index.tsx';
$content = file_get_contents($file);

// 1. Add useState, useEffect if not already imported (it's likely imported, let's just use it in the component).
// Let's find the component start:
$search1 = "const PenugasanPenyuluh: React.FC = () => {";
$replace1 = <<<'EOD'
const PenugasanPenyuluh: React.FC = () => {
  const [penugasanData, setPenugasanData] = useState<PenugasanData[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchPenugasan = async () => {
      try {
        const token = localStorage.getItem('token');
        const API_URL = import.meta.env.VITE_API_PELAKSANAAN_URL || 'http://127.0.0.1:8000/api';
        const res = await fetch(`${API_URL}/penugasan`, {
          headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
        });
        const json = await res.json();
        setPenugasanData(json.data || []);
      } catch (e) {
        console.error(e);
      } finally {
        setIsLoading(false);
      }
    };
    fetchPenugasan();
  }, []);
EOD;

$content = str_replace($search1, $replace1, $content);

// 2. Replace NEW_MOCK_DATA.map with penugasanData.map
// Wait, we need to handle activeTab filtering.
// Actually, let's just replace NEW_MOCK_DATA.map with penugasanData.map
$search2 = "{NEW_MOCK_DATA.map((item, index) => (";
$replace2 = "{penugasanData.map((item, index) => (";
$content = str_replace($search2, $replace2, $content);

file_put_contents($file, $content);
echo "index.tsx patched successfully.";
