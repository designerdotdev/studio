{{-- The drag seam on a docked list's inner edge (Content's collections,
     Code's files). A shield covers the window while it is held so the canvas
     iframe can't swallow the pointer. --}}
<div
    class="s-panel-seam"
    role="separator"
    aria-label="Resize the list"
    @mousedown.prevent="
        const dock = $el.parentElement;
        const shield = document.createElement('div');
        shield.className = 's-drag-shield';
        document.body.appendChild(shield);
        const move = (event) => $store.studio.setDockWidth(event.clientX - dock.getBoundingClientRect().left);
        const stop = () => {
            shield.remove();
            document.removeEventListener('mousemove', move);
            document.removeEventListener('mouseup', stop);
            window.removeEventListener('blur', stop);
            document.body.classList.remove('select-none');
        };
        document.body.classList.add('select-none');
        document.addEventListener('mousemove', move);
        document.addEventListener('mouseup', stop);
        window.addEventListener('blur', stop);
    "
></div>
