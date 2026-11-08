<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f5f0">
    <meta name="description" content="Dale lugar a eso que querés. Conocé Cali: electrodomésticos para la cocina, el lavado y los pequeños momentos de tu hogar.">
    <title>Cali — Electrodomésticos para tu hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="landing">
    @php
        $panelUrl = request()->getScheme().'://'.config('domains.panel').(! in_array(request()->getPort(), [80, 443]) ? ':'.request()->getPort() : '');
        $collections = [
            ['id' => 'cocina', 'number' => '01', 'name' => 'Una cocina con más posibilidades.', 'category' => 'Cocina y refrigeración', 'image' => 'fridge', 'alt' => 'Heladera abierta con alimentos organizados en su interior', 'description' => 'Heladeras, freezers, cocinas y hornos. Encontrá ese aliado que hace más fácil cocinar todos los días.', 'detail' => 'Antes de elegir, revisá las medidas de tu espacio, la capacidad que necesitás y el consumo energético.'],
            ['id' => 'lavado', 'number' => '02', 'name' => 'Menos tareas. Más tiempo para vos.', 'category' => 'Lavado', 'image' => 'laundry', 'alt' => 'Lavarropas de carga frontal en un espacio de lavado', 'description' => 'Lavarropas y soluciones de lavado para que la rutina de casa sea un poco más simple.', 'detail' => 'Tené en cuenta la capacidad de carga, las conexiones disponibles y las dimensiones del lugar de instalación.'],
            ['id' => 'pequenos', 'number' => '03', 'name' => 'Pequeños cambios, grandes momentos.', 'category' => 'Pequeños electrodomésticos', 'image' => 'coffee', 'alt' => 'Preparación de café en una cafetera espresso', 'description' => 'Cafeteras, pavas y pequeños electrodomésticos que le ponen algo especial a tu día.', 'detail' => 'Pensá en tu rutina, las funciones que realmente usás y el espacio disponible en tu mesada.'],
        ];
    @endphp

    <svg class="icon-definitions" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <symbol id="icon-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></symbol>
        <symbol id="icon-bag" viewBox="0 0 24 24"><path d="M5 7h14l1 14H4L5 7Zm3 0V6a4 4 0 0 1 8 0v1"/></symbol>
        <symbol id="icon-spark" viewBox="0 0 24 24"><path d="m12 2 2.5 7.5L22 12l-7.5 2.5L12 22l-2.5-7.5L2 12l7.5-2.5L12 2Z"/></symbol>
        <symbol id="icon-wallet" viewBox="0 0 24 24"><path d="M20 8V5H5a3 3 0 0 0 0 6h15v10H5a3 3 0 0 1-3-3V8m18 7h-5v3h5"/></symbol>
        <symbol id="icon-home" viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v11h-7v-7h-4v7H3V10Z"/></symbol>
    </svg>

    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <div class="announcement">Un hogar más tuyo. Un día a día más fácil. <span>Eso es Cali.</span></div>
    <header class="site-header">
        <div class="container header-inner">
            <a class="wordmark" href="#inicio" aria-label="Cali, inicio">cali<span>.</span></a>
            <nav class="desktop-nav" aria-label="Navegación principal">
                <a href="#colecciones">Para tu hogar</a>
                <a href="#por-que-cali">Por qué Cali</a>
                <a href="#cuotas">A tu manera</a>
            </nav>
            <a class="system-link" href="{{ $panelUrl }}">Ingresar al sistema <svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" aria-label="Abrir menú" hidden><span></span><span></span></button>
        </div>
        <nav id="mobile-nav" class="mobile-nav" aria-label="Navegación móvil" hidden>
            <a href="#colecciones">Para tu hogar</a>
            <a href="#por-que-cali">Por qué Cali</a>
            <a href="#cuotas">A tu manera</a>
            <a href="{{ $panelUrl }}">Ingresar al sistema ↗</a>
        </nav>
    </header>

    <main id="contenido">
        <section id="inicio" class="hero container">
            <div class="hero-copy">
                <p class="eyebrow"><span class="status-dot"></span> ELECTRO PARA LA VIDA REAL</p>
                <h1>Tu casa.<br>Tu mundo.<br><span>Más posibilidades.</span></h1>
                <p class="hero-description">Para los primeros cafés, las recetas de siempre y todo lo que está por venir. Electrodomésticos que se sienten como en casa.</p>
                <div class="hero-actions">
                    <a class="button button-dark" href="#colecciones">Encontrá tu próximo electro <svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></a>
                    <a class="text-link" href="#cuotas">Conocé más <span aria-hidden="true">↗</span></a>
                </div>
                <div class="hero-note"><svg class="icon" aria-hidden="true"><use href="#icon-home"/></svg><span>Grandes ideas para los pequeños momentos.</span></div>
            </div>
            <div class="hero-visual">
                <img class="hero-image" src="{{ asset('images/cali/kitchen.jpg') }}" alt="Cocina luminosa con electrodomésticos y una mesada preparada para cocinar" width="1600" height="1067" fetchpriority="high">
                <div class="image-shade"></div>
                <div class="hero-image-caption"><span>ESPACIOS QUE SE DISFRUTAN</span><p>Lo cotidiano también<br>puede ser extraordinario.</p></div>
                <div class="hero-sticker"><svg class="icon" aria-hidden="true"><use href="#icon-spark"/></svg><span>Más hogar.<br><strong>Más vos.</strong></span></div>
                <span class="image-index" aria-hidden="true">01 / CALI EN CASA</span>
            </div>
        </section>

        <div class="values-strip container" aria-label="Nuestra propuesta">
            <div><svg class="icon" aria-hidden="true"><use href="#icon-bag"/></svg><span>Electro para cada espacio</span></div>
            <div><svg class="icon" aria-hidden="true"><use href="#icon-wallet"/></svg><span>Conocé las opciones en cuotas</span></div>
            <div><svg class="icon" aria-hidden="true"><use href="#icon-spark"/></svg><span>Tu próximo cambio empieza acá</span></div>
        </div>

        <section id="colecciones" class="collections container section-space">
            <div class="section-heading" data-reveal>
                <div><p class="eyebrow">UN ELECTRO, MUCHAS POSIBILIDADES</p><h2>¿Qué espacio<br>querés transformar?</h2></div>
                <p>Lo que necesitás, para la vida que tenés.<br>Descubrí ideas para cada rincón de tu casa.</p>
            </div>
            <div class="collection-filters" role="group" aria-label="Filtrar colecciones" hidden>
                <button type="button" data-filter="all" aria-pressed="true">Todo para tu hogar</button>
                <button type="button" data-filter="cocina" aria-pressed="false">Cocina y refrigeración</button>
                <button type="button" data-filter="lavado" aria-pressed="false">Lavado</button>
                <button type="button" data-filter="pequenos" aria-pressed="false">Pequeños electro</button>
            </div>
            <p class="sr-only" aria-live="polite" id="filter-status"></p>
            <div class="collection-grid">
                @foreach ($collections as $collection)
                    <article class="collection-card" data-category="{{ $collection['id'] }}" data-reveal>
                        <div class="collection-image-wrap">
                            <img src="{{ asset('images/cali/'.$collection['image'].'.jpg') }}" alt="{{ $collection['alt'] }}" width="800" height="900" loading="lazy" decoding="async">
                            <span class="collection-number">/ {{ $collection['number'] }}</span>
                            <span class="collection-label">{{ $collection['category'] }}</span>
                        </div>
                        <h3>{{ $collection['name'] }}</h3>
                        <p>{{ $collection['description'] }}</p>
                        <details class="collection-details"><summary>Qué tener en cuenta <span aria-hidden="true">+</span></summary><p>{{ $collection['detail'] }}</p></details>
                    </article>
                @endforeach
            </div>
            <p class="collection-disclaimer">Imágenes ilustrativas. Los modelos y la disponibilidad de productos pueden variar.</p>
        </section>

        <section id="por-que-cali" class="brand-story section-space">
            <div class="container story-grid">
                <div class="story-image" data-reveal><img src="{{ asset('images/cali/living.jpg') }}" alt="Living cálido y luminoso con sillones y detalles de madera" width="1200" height="1000" loading="lazy" decoding="async"><span class="story-image-label">LA MEJOR PARTE ES ESTAR EN CASA.</span></div>
                <div class="story-copy" data-reveal>
                    <p class="eyebrow">HOLA, SOMOS CALI</p>
                    <h2>No se trata solo<br>de electrodomésticos.<br><span>Se trata de vivir mejor.</span></h2>
                    <p>Es ese café antes de salir. La ropa lista para una nueva semana. La cena que termina en sobremesa.</p>
                    <p>En Cali creemos que las cosas que elegís para tu casa tienen que acompañarte. Por eso, ponemos el foco en lo que importa: hacer más simple tu día a día.</p>
                    <a class="text-link" href="#preguntas">Conocé cómo empezar <svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></a>
                </div>
            </div>
        </section>

        <section id="cuotas" class="container section-space">
            <div class="finance-card" data-reveal>
                <div class="finance-copy"><p class="eyebrow">ESE CAMBIO QUE TENÉS EN MENTE</p><h2>Tu próximo electro.<br><span>A tu manera.</span></h2><p>Renovar tu casa empieza por elegir bien. Conocé las opciones de financiación y encontrá una alternativa para vos.</p><a class="button button-dark" href="#preguntas">Cómo dar el primer paso <svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></a><small>La financiación está sujeta a evaluación y a las condiciones vigentes de cada operación.</small></div>
                <div class="finance-art" aria-hidden="true"><span class="orbit orbit-one"></span><span class="orbit orbit-two"></span><div class="finance-paper"><span class="paper-brand">cali.</span><svg class="icon" aria-hidden="true"><use href="#icon-spark"/></svg><span class="paper-label">PRÓXIMO DESTINO</span><strong>Tu hogar.</strong><span class="paper-line"></span><span class="paper-bottom">MENOS VUELTAS. MÁS POSIBILIDADES. ↗</span></div><div class="finance-pill"><span class="status-dot"></span> Hacé lugar a lo nuevo</div></div>
            </div>
        </section>

        <section id="preguntas" class="container faq-section section-space">
            <div data-reveal><p class="eyebrow">TODO UN POCO MÁS CLARO</p><h2>Antes de elegir,<br>está bueno saber.</h2><p class="faq-intro">Algunas respuestas para empezar<br>a imaginar tu próximo cambio.</p></div>
            <div class="faq-list" data-reveal>
                <details><summary>¿Qué puedo encontrar en Cali?<span aria-hidden="true">+</span></summary><p>Electrodomésticos para cocina, refrigeración, lavado y pequeños momentos de todos los días. Las colecciones de esta página son orientativas; los productos y modelos dependen de la disponibilidad.</p></details>
                <details><summary>¿Cómo elijo el electrodoméstico indicado?<span aria-hidden="true">+</span></summary><p>Empezá por las medidas de tu espacio, el uso que le vas a dar y la capacidad que necesitás. Revisá también las conexiones, la eficiencia energética y las especificaciones de cada modelo antes de decidir.</p></details>
                <details><summary>¿Qué tengo que saber sobre las cuotas?<span aria-hidden="true">+</span></summary><p>Antes de confirmar una compra, solicitá las condiciones vigentes: anticipo, cantidad y monto de cuotas, costo financiero total y requisitos. Las opciones de financiación están sujetas a evaluación.</p></details>
                <details><summary>¿Puedo comprar desde esta página?<span aria-hidden="true">+</span></summary><p>Por ahora, este sitio es una presentación de Cali y sus categorías. No procesa compras ni pagos online. El acceso al sistema es para usuarios autorizados del negocio.</p></details>
            </div>
        </section>

        <section class="closing-banner"><div class="container"><svg class="icon" aria-hidden="true"><use href="#icon-spark"/></svg><p>Tu hogar tiene mucho por vivir.</p><a href="#inicio" aria-label="Volver al inicio"><svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></a></div></section>
    </main>

    <footer class="site-footer container">
        <div class="footer-top"><div><a class="wordmark" href="#inicio" aria-label="Cali, inicio">cali<span>.</span></a><p>Electrodomésticos para tu hogar.<br>Posibilidades para tu vida.</p></div><nav aria-label="Navegación del pie"><a href="#colecciones">Para tu hogar</a><a href="#por-que-cali">Conocé Cali</a><a href="#preguntas">Preguntas frecuentes</a></nav><a class="footer-system" href="{{ $panelUrl }}">Ingresar al sistema <span aria-hidden="true">↗</span></a></div>
        <div class="footer-bottom"><span>© {{ date('Y') }} Cali. Todos los derechos reservados.</span><span>Hecho para sentirse en casa.</span></div>
    </footer>
</body>
</html>
