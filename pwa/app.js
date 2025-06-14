function atualizarLocalizacao() {
  const status = document.getElementById('status');
  const linkMaps = document.getElementById('link-maps');
  const mapFrame = document.getElementById('map-frame');

  if (!navigator.geolocation) {
    status.textContent = 'Geolocalização não é suportada pelo navegador.';
    return;
  }

  navigator.geolocation.getCurrentPosition(
    async (position) => {
      const latitude = position.coords.latitude;
      const longitude = position.coords.longitude;

      status.textContent = `Latitude: ${latitude}, Longitude: ${longitude}`;
      
      const mapsUrl = `https://www.google.com/maps?q=${latitude},${longitude}`;
      linkMaps.href = mapsUrl;

      const embedUrl = `https://maps.google.com/maps?q=${latitude},${longitude}&z=15&output=embed`;
      mapFrame.src = embedUrl;

      try {
        const data = new URLSearchParams();
        data.append('latitude', latitude);
        data.append('longitude', longitude);

        const response = await fetch('salvar_localizacao.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: data.toString()
        });

        const resultado = await response.text();
        console.log('Servidor respondeu:', resultado);
      } catch (erro) {
        console.error('Erro ao enviar para o servidor:', erro);
      }
    },
    (error) => {
      status.textContent = 'Erro ao obter localização.' + error.message;
      console.error(error);
    }
  );
}

atualizarLocalizacao();
setInterval(atualizarLocalizacao, 10000);
