<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Rastreamento em Tempo Real</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Leaflet.awesome-markers/2.0.2/leaflet.awesome-markers.css" />
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
        h2 { text-align: center; margin: 10px 0; }
        #map { 
            height: 90vh; 
            width: 100%; 
            float: left;
        }
        #legend {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            z-index: 1000;
            max-height: 85vh;
            overflow-y: auto;
            width: 200px;
        }
        .legend-item {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 3px 0;
            transition: background-color 0.2s ease;
        }
        .legend-item:hover {
            background-color: #f0f0f0;
        }
        .legend-color-box {
            width: 20px;
            height: 20px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
    </style>
</head>
<body>

<h2>Rastreamento de Localização</h2>
<div id="map"></div>
<div id="legend">
    <h4>Legenda de Usuários</h4>
    <div id="legend-content"></div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Leaflet.awesome-markers/2.0.2/leaflet.awesome-markers.min.js"></script>
<script>
    const usuario = prompt("Digite seu nome ou identificador:");
    
    let currentMarker; 
    let currentAccuracyCircle; 

    const otherMarkersMap = new Map(); 

    async function obterIP() {
        try {
            const resp = await fetch('https://api.ipify.org?format=json');
            const data = await resp.json();
            return data.ip;
        } catch (error) {
            console.error('Erro ao obter IP:', error);
            return '0.0.0.0'; 
        }
    }

    // A função salvarLocalizacao agora aceita um parâmetro 'endereco'
    async function salvarLocalizacao(usuario, ip, latitude, longitude, endereco, dispositivo) { // <--- Adicionado 'endereco'
        try {
            const resp = await fetch('salvar_localizacao.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ usuario, ip, latitude, longitude, endereco, dispositivo }) // <--- Enviando 'endereco'
            });
            if (!resp.ok) {
                const errorText = await resp.text(); 
                throw new Error(`HTTP error! status: ${resp.status} - ${errorText}`);
            }
            const result = await resp.json();
            console.log('Dados salvos no servidor:', result);
        } catch (error) {
            console.error('Erro ao salvar localização:', error);
            alert('Erro ao salvar localização no servidor: ' + error.message);
        }
    }

    /**
     * Função para obter o endereço de uma coordenada usando a Nominatim API.
     * @param {number} lat Latitude
     * @param {number} lon Longitude
     * @returns {Promise<string>} Uma Promise que resolve com o endereço ou uma mensagem de erro.
     */
    async function getAddress(lat, lon) {
        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}&zoom=18&addressdetails=1`, {
                headers: {
                    'User-Agent': 'MeuAppDeRastreamento/1.0 (seuemail@example.com)' // OBRIGATÓRIO!
                }
            });
            
            if (!response.ok) {
                const errorBody = await response.text();
                throw new Error(`Erro na API Nominatim: ${response.status} - ${errorBody}`);
            }
            
            const data = await response.json();
            return data.display_name || `Lat: ${lat.toFixed(6)}, Lon: ${lon.toFixed(6)} (Endereço não detalhado)`;
        } catch (error) {
            console.warn('Erro ao obter endereço para', lat, lon, ':', error);
            return `Lat: ${lat.toFixed(6)}, Lon: ${lon.toFixed(6)} (Endereço indisponível)`;
        }
    }

    async function iniciarLocalizacao() {
        if (!navigator.geolocation) {
            alert('Geolocalização não é suportada por este navegador.');
            return;
        }

        const ip = await obterIP();
        const dispositivo = navigator.userAgent.substring(0, 100);

        navigator.geolocation.watchPosition(async (pos) => {
            const { latitude, longitude, accuracy } = pos.coords;

            map.setView([latitude, longitude], 15);

            if (currentMarker) {
                map.removeLayer(currentMarker);
            }
            if (currentAccuracyCircle) {
                map.removeLayer(currentAccuracyCircle);
            }

            const myIcon = L.AwesomeMarkers.icon({
                icon: 'user', 
                prefix: 'fa', 
                markerColor: 'blue', 
                iconColor: 'white' 
            });

            const myAddress = await getAddress(latitude, longitude); // Obtém o endereço

            currentMarker = L.marker([latitude, longitude], { icon: myIcon }).addTo(map)
                .bindPopup(`<strong>Você:</strong><br>Endereço: ${myAddress}<br>Precisão: ${accuracy.toFixed(2)} metros`)
                .openPopup(); 

            currentAccuracyCircle = L.circle([latitude, longitude], {
                radius: 10, 
                color: 'blue',
                fillColor: '#0000ff',
                fillOpacity: 0.2
            }).addTo(map);
            
            // Chama salvarLocalizacao com o endereço
            await salvarLocalizacao(usuario, ip, latitude, longitude, myAddress, dispositivo); // <--- Passando myAddress
            
        }, (err) => {
            console.error('Erro ao obter localização:', err);
            let errorMessage = "Não foi possível obter sua localização. ";
            switch (err.code) {
                case err.PERMISSION_DENIED:
                    errorMessage += "Permissão negada pelo usuário. Por favor, permita o acesso à localização.";
                    break;
                case err.POSITION_UNAVAILABLE:
                    errorMessage += "Localização indisponível. Verifique suas configurações de localização.";
                    break;
                case err.TIMEOUT:
                    errorMessage += "Tempo limite excedido. Tente em um local com melhor sinal de GPS/Wi-Fi.";
                    break;
                default:
                    errorMessage += `Erro desconhecido: ${err.message}`;
            }
            alert(errorMessage + "\nCertifique-se de que a localização está habilitada para este site e dispositivo.");
        }, {
            enableHighAccuracy: false,
            timeout: 20000,          
            maximumAge: 0            
        });
    }

    const map = L.map('map').setView([-22.7291, -47.6493], 12); // Coordenadas aproximadas de Piracicaba

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    let otherLocationMarkers = L.featureGroup().addTo(map);
    let otherLocationPolylines = L.featureGroup().addTo(map);

    const assignedColors = {}; 
    const availableColors = ['red', 'orange', 'green', 'purple', 'darkblue', 'cadetblue', 'darkred', 'darkgreen', 'lightblue'];
    let colorIndex = 0;

    async function carregarLocalizacoes() {
        try {
            const resp = await fetch('get_localizacoes.php');
            if (!resp.ok) {
                const errorText = await resp.text();
                throw new Error(`Erro ao buscar dados: ${resp.status} - ${errorText}`);
            }
            const dados = await resp.json();

            otherLocationMarkers.clearLayers();
            otherLocationPolylines.clearLayers();
            otherMarkersMap.clear();

            const linhasPorUsuarioDispositivo = {}; 
            const legendContent = document.getElementById('legend-content');
            legendContent.innerHTML = ''; 

            const usersAddedToLegend = new Set(); 

            // Para otimizar as requisições de endereço, se o endereço JÁ estiver no BD, não precisamos
            // chamar a Nominatim API novamente.
            const processingPromises = dados.map(async loc => {
                const chave = `${loc.usuario}-${loc.dispositivo}`;
                
                if (!assignedColors[chave]) {
                    assignedColors[chave] = availableColors[colorIndex % availableColors.length];
                    colorIndex++;
                }
                const color = assignedColors[chave];

                if (!linhasPorUsuarioDispositivo[chave]) {
                    linhasPorUsuarioDispositivo[chave] = [];
                }
                linhasPorUsuarioDispositivo[chave].push([parseFloat(loc.latitude), parseFloat(loc.longitude)]);

                const dataChegada = new Date(loc.data_chegada).toLocaleString('pt-BR');
                const dataSaida = loc.data_saida ? new Date(loc.data_saida).toLocaleString('pt-BR') : 'Ainda no local';

                const otherUserIcon = L.AwesomeMarkers.icon({
                    icon: 'user',
                    prefix: 'fa',
                    markerColor: color,
                    iconColor: 'white'
                });

                // --- AGORA VERIFICAMOS SE O ENDEREÇO JÁ VEIO DO BD ---
                // Se loc.endereco existir e não for vazio, use ele.
                // Caso contrário, chame a API Nominatim.
                const address = loc.endereco && loc.endereco.trim() !== '' 
                                ? loc.endereco 
                                : await getAddress(parseFloat(loc.latitude), parseFloat(loc.longitude));

                const marker = L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)], { icon: otherUserIcon }).bindPopup(
                    `<strong>Usuário:</strong> ${loc.usuario}<br>` +
                    `<strong>Dispositivo:</strong> ${loc.dispositivo}<br>` +
                    `<strong>Endereço:</strong> ${address}<br>` + // Usando o endereço
                    `<strong>Chegada:</strong> ${dataChegada}<br>` +
                    `<strong>Saída:</strong> ${dataSaida}`
                );
                otherLocationMarkers.addLayer(marker);
                otherMarkersMap.set(chave, marker);

                if (!usersAddedToLegend.has(chave)) {
                    const legendItem = document.createElement('div');
                    legendItem.className = 'legend-item';
                    legendItem.innerHTML = `
                        <span class="legend-color-box" style="background-color: ${color};"></span>
                        ${loc.usuario} (${loc.dispositivo.substring(0,25)}...)
                    `;
                    legendItem.addEventListener('click', () => {
                        const targetMarker = otherMarkersMap.get(chave);
                        if (targetMarker) {
                            map.setView(targetMarker.getLatLng(), 15);
                            targetMarker.openPopup();
                        }
                    });
                    legendContent.appendChild(legendItem);
                    usersAddedToLegend.add(chave);
                }
                return { marker, chave }; // Retorna para Promise.all
            });

            const markersData = await Promise.all(processingPromises); 
            // `markersData` pode ser usado se precisarmos de algo após todos os marcadores serem criados.
            // Para o Polyline, podemos usar linhasPorUsuarioDispositivo que já está sendo preenchido.

            for (let chave in linhasPorUsuarioDispositivo) {
                const color = assignedColors[chave]; 
                const polyline = L.polyline(linhasPorUsuarioDispositivo[chave], {
                    color: color,
                    weight: 3,
                    opacity: 0.6
                });
                otherLocationPolylines.addLayer(polyline);
            }

        } catch (error) {
            console.error('Erro ao carregar localizações:', error);
            alert('Erro ao carregar localizações: ' + error.message);
        }
    }

    (async () => {
        await iniciarLocalizacao(); 
        await carregarLocalizacoes(); 
        setInterval(carregarLocalizacoes, 30000); 
    })();

</script>
</body>
</html>