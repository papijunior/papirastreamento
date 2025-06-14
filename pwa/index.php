<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Rastreamento em Tempo Real</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <style>
    #map { height: 90vh; }
  </style>
</head>
<body>

<h2>Rastreamento de Localização</h2>
<div id="map"></div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const usuario = prompt("Digite seu nome ou identificador:");
const dispositivo = navigator.userAgent;

async function obterIP() {
  const resp = await fetch('https://api.ipify.org?format=json');
  const data = await resp.json();
  return data.ip;
}

async function salvarLocalizacao(usuario, ip, latitude, longitude, dispositivo) {
  try {
    const resp = await fetch('salvar_localizacao.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ usuario, ip, latitude, longitude, dispositivo })
    });
    if (!resp.ok) throw new Error(`HTTP error! status: ${resp.status}`);
    const result = await resp.json();
    console.log('Salvo:', result);
  } catch (error) {
    console.error('Erro ao salvar localização:', error);
    alert('Erro ao salvar localização.' + error.message);
  }
}

async function iniciarLocalizacao() {
  if (!navigator.geolocation) {
    alert('Geolocalização não é suportada.');
    return;
  }

  const ip = await obterIP();

  navigator.geolocation.watchPosition(async (pos) => {
    const { latitude, longitude } = pos.coords;
    //const dispositivo = 'navigator.userAgent'; // exemplo: pode personalizar depois
    const dispositivo = navigator.userAgent.substring(0, 100); // exemplo: pode personalizar depois
    alert('latitude' + latitude);
    await salvarLocalizacao(usuario, ip, latitude, longitude, dispositivo);
    
  }, (err) => {
    console.error('Erro ao obter localização:', err);
  }, {
    enableHighAccuracy: true,
    timeout: 20000,
    maximumAge: 0
  });
}

const map = L.map('map').setView([-23.55, -46.63], 12); // centro inicial

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap contributors'
}).addTo(map);

async function carregarLocalizacoes() {
  try {
    const resp = await fetch('get_localizacoes.php');
    if (!resp.ok) throw new Error('Erro ao buscar dados');
    const dados = await resp.json();

    const cores = {};
    const linhas = {};
    const paleta = ['red', 'blue', 'green', 'orange', 'purple', 'brown', 'black', 'pink'];

    dados.forEach(loc => {
      const chave = `${loc.usuario}-${loc.dispositivo}`;
      if (!cores[chave]) {
        cores[chave] = paleta[Object.keys(cores).length % paleta.length];
        linhas[chave] = [];
      }

      linhas[chave].push([parseFloat(loc.latitude), parseFloat(loc.longitude)]);

      L.circleMarker([loc.latitude, loc.longitude], {
        radius: 6,
        color: cores[chave],
        fillColor: cores[chave],
        fillOpacity: 0.6
      }).addTo(map).bindPopup(
        `<strong>${loc.usuario}</strong><br>${loc.dispositivo}<br><small>Chegada: ${loc.data_chegada}<br>Saída: ${loc.data_saida ?? '-'}</small>`
      );
    });

    for (let chave in linhas) {
      L.polyline(linhas[chave], {
        color: cores[chave],
        weight: 3,
        opacity: 0.6
      }).addTo(map);
    }

  } catch (error) {
    console.error('Erro ao carregar localizações:', error);
  }
}

iniciarLocalizacao();
carregarLocalizacoes();
setInterval(carregarLocalizacoes, 30000); // atualiza o mapa a cada 30s
</script>
</body>
</html>