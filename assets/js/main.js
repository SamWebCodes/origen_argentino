/**
 * Origen Argentino — JavaScript principal
 * Vanilla ES6, sin librerías. Reproduce los comportamientos del
 * sitio original: menú desplegable, carrusel infinito, trazo animado
 * de los titulares, animaciones de entrada y widget de contacto.
 */
'use strict';

(() => {
  const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* ============================================================
     Menú desplegable (tablet y móvil)
     ============================================================ */
  (() => {
    const toggle = document.querySelector('.nav-toggle');
    const menu = document.querySelector('.site-nav-desplegable');
    if (!toggle || !menu) {
      return;
    }

    /**
     * Abre o cierra el desplegable manteniendo sincronizados
     * aria-expanded y el estado inert de los enlaces.
     * @param {boolean} abrir
     */
    function alternar(abrir) {
      toggle.setAttribute('aria-expanded', String(abrir));
      toggle.setAttribute('aria-label', abrir ? 'Cerrar menú' : 'Abrir menú');
      menu.classList.toggle('abierto', abrir);
      menu.inert = !abrir;
    }

    toggle.addEventListener('click', () => {
      alternar(toggle.getAttribute('aria-expanded') !== 'true');
    });

    // Al elegir una opción el menú se cierra
    menu.querySelectorAll('a').forEach((enlace) => {
      enlace.addEventListener('click', () => alternar(false));
    });

    // Escape cierra y devuelve el foco al botón
    document.addEventListener('keydown', (evento) => {
      if (evento.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        alternar(false);
        toggle.focus();
      }
    });
  })();

  /* ============================================================
     Titulares con trazo animado
     El ciclo CSS arranca cuando el titular entra en pantalla.
     ============================================================ */
  (() => {
    const titulares = Array.from(document.querySelectorAll('.titular'))
      .filter((titular) => titular.querySelector('.titular-trazo'));
    if (!titulares.length) {
      return;
    }

    if (!('IntersectionObserver' in window)) {
      titulares.forEach((titular) => titular.classList.add('animado'));
      return;
    }

    const observador = new IntersectionObserver((entradas) => {
      entradas.forEach((entrada) => {
        if (entrada.isIntersecting) {
          entrada.target.classList.add('animado');
          observador.unobserve(entrada.target);
        }
      });
    }, { rootMargin: '0px 0px -100px 0px' });

    titulares.forEach((titular) => observador.observe(titular));
  })();

  /* ============================================================
     Animaciones de entrada (slideInDown, tada, bounce)
     ============================================================ */
  (() => {
    const elementos = document.querySelectorAll('.anima');
    if (!elementos.length) {
      return;
    }

    if (!('IntersectionObserver' in window) || movimientoReducido.matches) {
      elementos.forEach((elemento) => elemento.classList.add('anima-activa'));
      return;
    }

    const observador = new IntersectionObserver((entradas) => {
      entradas.forEach((entrada) => {
        if (entrada.isIntersecting) {
          entrada.target.classList.add('anima-activa');
          observador.unobserve(entrada.target);
        }
      });
    }, { rootMargin: '0px 0px -100px 0px' });

    elementos.forEach((elemento) => observador.observe(elemento));
  })();

  /* ============================================================
     Carrusel de galería
     Bucle infinito por clonado, 4/3/2 visibles según el ancho,
     avance automático cada 4 s y transición de 400 ms.
     ============================================================ */
  (() => {
    const carrusel = document.querySelector('.carrusel');
    if (!carrusel) {
      return;
    }

    const pista = carrusel.querySelector('.carrusel-pista');
    const flechaPrev = carrusel.querySelector('.carrusel-flecha-prev');
    const flechaNext = carrusel.querySelector('.carrusel-flecha-next');
    const originales = Array.from(pista.children);
    const total = originales.length;
    if (!total) {
      return;
    }

    const AUTOPLAY_MS = 4000;
    const VELOCIDAD_MS = 400;

    /**
     * Copia el juego de imágenes conservando su orden.
     * @return {DocumentFragment}
     */
    function clonarJuego() {
      const fragmento = document.createDocumentFragment();
      originales.forEach((item) => {
        const clon = item.cloneNode(true);
        clon.setAttribute('aria-hidden', 'true');
        fragmento.appendChild(clon);
      });
      return fragmento;
    }

    // Un juego de clones antes y otro después del bloque real
    pista.insertBefore(clonarJuego(), pista.firstChild);
    pista.appendChild(clonarJuego());

    let indice = total;
    let visibles = calcularVisibles();
    let temporizador = null;
    let autoplayActivo = !movimientoReducido.matches;

    /**
     * Número de imágenes visibles según el ancho de la ventana.
     * Mismos cortes que el diseño original: 4 · 3 · 2.
     * @return {number}
     */
    function calcularVisibles() {
      const ancho = window.innerWidth;
      if (ancho <= 767) {
        return 2;
      }
      if (ancho <= 1024) {
        return 3;
      }
      return 4;
    }

    /**
     * Coloca la pista en el índice actual.
     * @param {boolean} conTransicion
     */
    function colocar(conTransicion) {
      pista.style.transition = conTransicion ? `transform ${VELOCIDAD_MS}ms ease` : 'none';
      pista.style.transform = `translateX(-${(indice * 100) / visibles}%)`;
    }

    /**
     * Avanza o retrocede una posición y recoloca sin salto
     * cuando se sale del bloque de clones.
     * @param {number} paso
     */
    function mover(paso) {
      indice += paso;
      colocar(true);
    }

    // Al terminar el desplazamiento se vuelve al bloque central
    pista.addEventListener('transitionend', () => {
      if (indice >= total * 2) {
        indice -= total;
        colocar(false);
      } else if (indice < total) {
        indice += total;
        colocar(false);
      }
    });

    /** Arranca el avance automático. */
    function iniciarAutoplay() {
      detenerAutoplay();
      if (autoplayActivo) {
        temporizador = window.setInterval(() => mover(1), AUTOPLAY_MS);
      }
    }

    /** Detiene el avance automático. */
    function detenerAutoplay() {
      window.clearInterval(temporizador);
      temporizador = null;
    }

    if (flechaPrev) {
      flechaPrev.addEventListener('click', () => {
        // Igual que el original: la interacción manual cancela el automático
        autoplayActivo = false;
        detenerAutoplay();
        mover(-1);
      });
    }

    if (flechaNext) {
      flechaNext.addEventListener('click', () => {
        autoplayActivo = false;
        detenerAutoplay();
        mover(1);
      });
    }

    carrusel.addEventListener('mouseenter', detenerAutoplay);
    carrusel.addEventListener('mouseleave', iniciarAutoplay);

    window.addEventListener('resize', () => {
      const nuevas = calcularVisibles();
      if (nuevas !== visibles) {
        visibles = nuevas;
        indice = total;
        colocar(false);
      }
    });

    colocar(false);
    iniciarAutoplay();
  })();

  /* ============================================================
     Widget flotante de contacto
     Se abre al pasar el cursor (escritorio) o al pulsar.
     ============================================================ */
  (() => {
    const widget = document.querySelector('.widget-contacto');
    if (!widget) {
      return;
    }

    const boton = widget.querySelector('.widget-contacto-boton');
    const canales = widget.querySelector('.widget-contacto-canales');
    if (!boton || !canales) {
      return;
    }

    /**
     * Muestra u oculta los canales de contacto.
     * @param {boolean} abrir
     */
    function alternar(abrir) {
      widget.classList.toggle('abierto', abrir);
      boton.setAttribute('aria-expanded', String(abrir));
      boton.setAttribute('aria-label', abrir ? 'Cerrar opciones de contacto' : 'Abrir opciones de contacto');
      canales.inert = !abrir;
    }

    boton.addEventListener('click', () => {
      alternar(boton.getAttribute('aria-expanded') !== 'true');
    });

    // En dispositivos con puntero fino el widget se abre al pasar por encima
    if (window.matchMedia('(hover: hover)').matches) {
      widget.addEventListener('mouseenter', () => alternar(true));
      widget.addEventListener('mouseleave', () => alternar(false));
    }

    widget.addEventListener('focusout', (evento) => {
      if (!widget.contains(evento.relatedTarget)) {
        alternar(false);
      }
    });

    document.addEventListener('keydown', (evento) => {
      if (evento.key === 'Escape' && boton.getAttribute('aria-expanded') === 'true') {
        alternar(false);
        boton.focus();
      }
    });
  })();
})();
