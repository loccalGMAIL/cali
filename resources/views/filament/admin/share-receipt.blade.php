{{--
    Comparte el PDF del comprobante con el menú de compartir del dispositivo (Web Share API), para
    mandarlo como archivo por WhatsApp. Lo usa la acción "Compartir PDF" de los cobros.

    El PDF se descarga al tocar el botón y queda guardado: si el navegador no deja abrir el menú
    porque la descarga demoró (pasa en iPhone), con el segundo toque se comparte enseguida.
--}}
<script>
    (() => {
        const files = new Map();

        const notify = (status, title, body = null) => {
            const notification = new FilamentNotification().title(title)[status]();

            if (body) {
                notification.body(body);
            }

            notification.send();
        };

        const notifyUnsupported = () => notify(
            'warning',
            'Este navegador no permite compartir archivos',
            'Usá el botón WhatsApp para mandar el enlace, o descargá el comprobante.',
        );

        const download = async (url, fileName) => {
            if (! files.has(url)) {
                const response = await fetch(url, { credentials: 'same-origin' });

                if (! response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                files.set(url, new File([await response.blob()], fileName, { type: 'application/pdf' }));
            }

            return files.get(url);
        };

        window.caliShareReceipt = async (url, fileName) => {
            if (! navigator.canShare) {
                notifyUnsupported();

                return;
            }

            let file;

            try {
                file = await download(url, fileName);
            } catch {
                notify('danger', 'No se pudo generar el comprobante', 'Probá de nuevo en unos segundos.');

                return;
            }

            if (! navigator.canShare({ files: [file] })) {
                notifyUnsupported();

                return;
            }

            try {
                // Solo el archivo: con texto, algunas versiones de WhatsApp mandan el texto y descartan el PDF.
                await navigator.share({ files: [file] });
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                if (error.name === 'NotAllowedError') {
                    notify('info', 'El comprobante está listo', 'Tocá «Compartir PDF» otra vez para enviarlo.');

                    return;
                }

                notify('danger', 'No se pudo compartir el comprobante');
            }
        };
    })();
</script>
