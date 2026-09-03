// Contoh FE. Disarankan FE tetap mengambil dari PHP backend, bukan langsung dari Python.

async function loadCriticalLandResult(projectId) {
  const response = await fetch(`/api/projects/${projectId}/result`);
  if (!response.ok) throw new Error('Gagal mengambil hasil analisis');
  const result = await response.json();

  const mapGeojsonUrl = result.map.critical_geojson;
  const tableRows = result.table;

  // Render mapGeojsonUrl ke Leaflet/MapLibre.
  // Render tableRows ke tabel dashboard.
  return { mapGeojsonUrl, tableRows, result };
}
