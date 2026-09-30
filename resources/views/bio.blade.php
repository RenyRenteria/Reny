<x-artist-page active="bio" title="Bio" description="Conoce la historia de Reny Rentería: cantante, pianista, productor musical y creativo multidisciplinario nacido en Colón, Panamá.">
    <section class="artist-hero" aria-labelledby="bio-title">
        <div class="artist-hero-copy">
            <p class="artist-eyebrow">Bio · Colón, Panamá</p>
            <h1 id="bio-title">Reny<br>Rentería</h1>
            <p class="artist-lead">Voz, música y una forma propia de estar en el escenario.</p>
            <p>Cantante, pianista, productor musical y creativo multidisciplinario. Una carrera que combina el talento vocal, la pasión por la escena y una visión artística profundamente humana.</p>
            <a class="artist-button" href="{{ route('music') }}">Escucha su música <span aria-hidden="true">↗</span></a>
        </div>
        <figure class="artist-hero-photo">
            <img src="{{ asset('images/photos/cover.jpg') }}" alt="Reny Rentería cantando frente a un micrófono" width="640" height="1136" fetchpriority="high">
            <figcaption>La voz detrás de la música.</figcaption>
        </figure>
    </section>

    <section class="artist-panel" aria-labelledby="bio-origins">
        <p class="artist-eyebrow">Sus raíces</p>
        <h2 id="bio-origins">De Colón al escenario</h2>
        <p>Reny Rentería nació el 9 de octubre de 1998 en Colón, Panamá, una ciudad que ha sido cuna de grandes voces y que marcó el inicio de su camino artístico.</p>
        <h3>Formación</h3>
        <p>Desde temprana edad, Reny cultivó su don por la música a través de una sólida preparación académica. Inició sus estudios de canto en la academia Escenario y en el INAC (Instituto Nacional de Cultura), ambos en Colón. Posteriormente perfeccionó su técnica vocal y desarrolló sus habilidades en el piano en la academia Acordes y Voces, donde construyó las bases de su versatilidad musical.</p>
    </section>

    <section class="artist-panel" aria-labelledby="bio-career">
        <p class="artist-eyebrow">Trayectoria</p>
        <h2 id="bio-career">Una vida entre música, televisión y teatro</h2>
        <p>Desde sus años escolares destacó al obtener premios en concursos de composición, canto y baile, demostrando un talento integral que lo acompañaría a lo largo de su vida artística.</p>
        <div class="artist-milestones">
            <article><span>2010</span><h3>Canta Conmigo</h3><p>5.º lugar en el programa de TVN, conquistando al público nacional.</p></article>
            <article><span>2014</span><h3>Pelaos con Salsa</h3><p>3.er lugar en el programa de Telemetro.</p></article>
            <article><span>En pantalla</span><h3>Televisión y cine</h3><p>Parte del elenco de Sueños de Verano (TVN, 2014) y participación en la película Historias del Canal.</p></article>
        </div>
        <h3>Teatro musical</h3>
        <p>Su faceta como intérprete teatral lo llevó a protagonizar y participar en destacadas producciones:</p>
        <ol class="artist-timeline">
            <li><span>2010</span> La Bella y La Bestia Jr.</li>
            <li><span>2011</span> Peter Pan, El Musical</li>
            <li><span>2012</span> Alicia en el País de Las Maravillas</li>
            <li><span>2013</span> Charlie y La Fábrica de Chocolates</li>
            <li><span>2016</span> Mamma Mía</li>
            <li><span>2022</span> In The Heights, El Musical</li>
        </ol>
        <p>Su versatilidad escénica también fue reconocida al obtener el 1.er lugar del concurso de interpretación escénica Panama is Burning, donde brilló por su autenticidad y presencia.</p>
    </section>

    <div class="artist-gallery" aria-label="Reny en el estudio y en escena">
        <figure><img src="{{ asset('images/photos/studio.jpg') }}" alt="Reny grabando voces con audífonos en el estudio" width="640" height="1136" loading="lazy" decoding="async"><figcaption>En el estudio: voz y creación.</figcaption></figure>
        <figure><img src="{{ asset('images/photos/performance.jpg') }}" alt="Reny cantando junto a una bailarina durante una presentación en televisión" width="640" height="1136" loading="lazy" decoding="async"><figcaption>En escena: música y movimiento.</figcaption></figure>
    </div>

    <section class="artist-panel" aria-labelledby="bio-stage">
        <p class="artist-eyebrow">Festivales y presentaciones</p>
        <h2 id="bio-stage">Desde Panamá, conectando con el mundo</h2>
        <p>Reny ha llevado su música a importantes plataformas nacionales e internacionales, incluyendo el Festival de La Rosa Dorada en Panamá y Colombia —producción propia—, Sofar Sounds en Panamá y Colombia, y Musicalion.</p>
        <p>Entre sus presentaciones más relevantes destaca su participación en Miss Universe Panamá en 2024 y 2026, evento que reafirmó su proyección como artista de gran impacto escénico.</p>
        <p>El 21 de septiembre de 2026 presentó Reny Renteria En Concierto en Ciudad de Panamá.</p>
        <a class="artist-button artist-button-secondary" href="{{ route('shows') }}">Ver próximos shows <span aria-hidden="true">↗</span></a>
    </section>

    <section class="artist-panel" aria-labelledby="bio-craft">
        <p class="artist-eyebrow">Habilidades y conocimientos</p>
        <h2 id="bio-craft">Un artista en todas sus dimensiones</h2>
        <ul class="artist-skills">
            <li>Dominio de voz y piano</li>
            <li>Teoría musical, vocalización y solfeo</li>
            <li>Técnica de respiración y colocación vocal</li>
            <li>Manejo de software y herramientas de edición de audio</li>
            <li>Adaptación y edición de tracks y pistas musicales</li>
            <li>Producción musical y producción de eventos</li>
            <li>Proyección escénica y baile</li>
            <li>Diseño y confección de moda</li>
        </ul>
    </section>

    <section class="artist-panel artist-philosophy" aria-labelledby="bio-sound">
        <p class="artist-eyebrow">Su propuesta artística</p>
        <h2 id="bio-sound">Celebrar aquello que nos hace únicos.</h2>
        <p>La música de Reny Rentería habla de amor, realidades sociales y la energía vibrante del funk carioca. Su sonido se mueve entre el funk carioca, el pop y el reggae, explorando también la salsa y la electrónica como territorios de experimentación.</p>
        <p>Más allá de los géneros, su arte está guiado por una filosofía clara: hacer que las personas se sientan vistas, celebrar aquello que nos hace únicos y convertir los sueños en realidad. Con cada presentación, Reny busca conectar, inspirar y recordarle a su público el valor de la autenticidad.</p>
    </section>

    <section class="artist-panel" aria-labelledby="bio-albums">
        <p class="artist-eyebrow">Discografía</p>
        <h2 id="bio-albums">La historia también se escucha</h2>
        <div class="artist-albums">
            <a href="{{ route('music') }}"><span>2025 · Álbum</span><strong>Work in Progress</strong><span>Escuchar <span aria-hidden="true">↗</span></span></a>
            <a href="{{ route('music') }}"><span>2026 · Álbum</span><strong>Take a bite</strong><span>Escuchar <span aria-hidden="true">↗</span></span></a>
        </div>
    </section>
</x-artist-page>
