/* =========================================================
   CRUD DE DESTINOS - Atlas Tours
   Conectado a Base_De_Datos.php (backend real, con sesión admin)
========================================================= */

/* -----------------------------------------------------------
   ⚠️ AJUSTA ESTA RUTA si tu página del panel no está un nivel
   por debajo de donde vive Base_De_Datos.php
----------------------------------------------------------- */
const BASE_URL   = "../php/Base_De_Datos.php";
// La imagen que devuelve el backend viene como "assets/img/destinos/xxx.jpg"
// relativo a la carpeta donde está Base_De_Datos.php (php/). Si BASE_URL
// cambia, ajusta también este prefijo para que coincida.
const IMG_PREFIX = "../php/";

// Placeholder de imagen embebido (SVG en base64) — no depende de ningún
// servicio externo, así que nunca puede fallar con error de conexión.
const IMAGEN_PLACEHOLDER = "data:image/svg+xml;base64," + btoa(`
<svg xmlns="http://www.w3.org/2000/svg" width="70" height="50" viewBox="0 0 70 50">
    <rect width="70" height="50" fill="#e5e7eb"/>
    <text x="35" y="28" font-family="Arial, sans-serif" font-size="8"
        fill="#6b7280" text-anchor="middle">Sin imagen</text>
</svg>
`);


/* =========================================================
   ELEMENTOS DEL DOM
========================================================= */

const tablaBody = document.getElementById("tablaBody");
const modal = document.getElementById("modalDestino");
const tituloModal = document.getElementById("tituloModal");
const formDestino = document.getElementById("formDestino");
const toast = document.getElementById("toast");

const btnNuevoDestino = document.getElementById("btnNuevoDestino");
const btnCancelar = document.getElementById("btnCancelar");

const campoId = document.getElementById("id_destino");
const campoNombre = document.getElementById("nombre");
const campoDescripcion = document.getElementById("descripcion");
const campoImagen = document.getElementById("imagen");
const campoImagenActual = document.getElementById("imagenActual");
const previewImagen = document.getElementById("previewImagen");
const campoTelefono = document.getElementById("telefono");
const campoEstado = document.getElementById("estado");

// Cache local de la última lista recibida del servidor (evita otra
// petición al abrir el modal de edición)
let destinosCache = [];


/* =========================================================
   PREVIEW DE IMAGEN (solo visual, el archivo real se envía
   tal cual con FormData al guardar)
========================================================= */

function mostrarPreview(src) {
    if (!src) {
        previewImagen.style.display = "none";
        previewImagen.src = "";
        return;
    }

    previewImagen.src = src;
    previewImagen.style.display = "block";
}

campoImagen.addEventListener("change", () => {
    const archivo = campoImagen.files[0];

    if (!archivo) return;

    if (!archivo.type.startsWith("image/")) {
        mostrarToast("El archivo debe ser una imagen", "error");
        campoImagen.value = "";
        return;
    }

    if (archivo.size > 2 * 1024 * 1024) {
        mostrarToast("La imagen es muy pesada (máx. 2MB)", "error");
        campoImagen.value = "";
        return;
    }

    const lector = new FileReader();
    lector.onload = () => mostrarPreview(lector.result);
    lector.readAsDataURL(archivo);
});


/* =========================================================
   OBTENER DESTINOS DESDE EL SERVIDOR
========================================================= */

async function obtenerDestinos() {
    try {
        const respuesta = await fetch(`${BASE_URL}?accion=listar_destinos`, {
            method: "GET",
            credentials: "same-origin"
        });

        const data = await respuesta.json();

        if (!data.exito) {
            mostrarToast(data.mensaje || "No se pudieron cargar los destinos", "error");
            return [];
        }

        return data.destinos;

    } catch (error) {
        console.error(error);
        mostrarToast("No se pudo conectar con el servidor", "error");
        return [];
    }
}


/* =========================================================
   RENDERIZAR TABLA
========================================================= */

async function renderizarTabla() {
    const destinos = await obtenerDestinos();
    destinosCache = destinos;

    tablaBody.innerHTML = "";

    if (destinos.length === 0) {
        tablaBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding: 25px; color:#6b7280;">
                    No hay destinos registrados.
                </td>
            </tr>
        `;
        return;
    }

    destinos.forEach(destino => {
        const fila = document.createElement("tr");
        const rutaImagen = destino.imagen ? IMG_PREFIX + destino.imagen : "";

        fila.innerHTML = `
            <td>${destino.id_destino}</td>
            <td>
                <img
                    src="${rutaImagen}"
                    alt="${destino.nombre}"
                    onerror="this.onerror=null; this.src=IMAGEN_PLACEHOLDER;">
            </td>
            <td>${destino.nombre}</td>
            <td>${destino.telefono || "-"}</td>
            <td>
                <span style="
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                    background: ${destino.estado === "Activo" ? "#dcfce7" : "#fee2e2"};
                    color: ${destino.estado === "Activo" ? "#166534" : "#b91c1c"};
                ">
                    ${destino.estado}
                </span>
            </td>
            <td>${destino.fecha_creacion || "-"}</td>
            <td>
                <button class="btn-editar" data-id="${destino.id_destino}" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <button class="btn-eliminar" data-id="${destino.id_destino}" title="Eliminar">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;

        tablaBody.appendChild(fila);
    });

    document.querySelectorAll(".btn-editar").forEach(btn => {
        btn.addEventListener("click", () => abrirModalEditar(Number(btn.dataset.id)));
    });

    document.querySelectorAll(".btn-eliminar").forEach(btn => {
        btn.addEventListener("click", () => eliminarDestino(Number(btn.dataset.id)));
    });
}


/* =========================================================
   MODAL: ABRIR / CERRAR
========================================================= */

function abrirModalNuevo() {
    tituloModal.textContent = "Nuevo destino";
    formDestino.reset();
    campoId.value = "";
    campoEstado.value = "Activo";
    campoImagenActual.value = "";
    mostrarPreview("");
    modal.classList.add("abierto");
}

function abrirModalEditar(id) {
    const destino = destinosCache.find(d => Number(d.id_destino) === id);

    if (!destino) return;

    tituloModal.textContent = "Editar destino";

    campoId.value = destino.id_destino;
    campoNombre.value = destino.nombre;
    campoDescripcion.value = destino.descripcion;
    campoTelefono.value = destino.telefono || "";
    campoEstado.value = destino.estado;

    // La imagen existente se conserva a menos que se elija un archivo nuevo
    campoImagen.value = "";
    campoImagenActual.value = destino.imagen;
    mostrarPreview(destino.imagen ? IMG_PREFIX + destino.imagen : "");

    modal.classList.add("abierto");
}

function cerrarModal() {
    modal.classList.remove("abierto");
    formDestino.reset();
    mostrarPreview("");
}


/* =========================================================
   GUARDAR (CREAR O EDITAR)
========================================================= */

async function guardarDestino(evento) {
    evento.preventDefault();

    const id = campoId.value;
    const esEdicion = Boolean(id);

    if (!esEdicion && !campoImagen.files[0]) {
        mostrarToast("Debes seleccionar una imagen", "error");
        return;
    }

    const formData = new FormData();
    formData.append("formulario", esEdicion ? "destino_editar" : "destino_crear");
    formData.append("nombre", campoNombre.value.trim());
    formData.append("descripcion", campoDescripcion.value.trim());
    formData.append("telefono", campoTelefono.value.trim());
    formData.append("estado", campoEstado.value);

    if (esEdicion) {
        formData.append("id_destino", id);
    }

    // Solo se envía el archivo si el usuario eligió uno nuevo
    if (campoImagen.files[0]) {
        formData.append("imagen", campoImagen.files[0]);
    }

    try {
        const respuesta = await fetch(BASE_URL, {
            method: "POST",
            credentials: "same-origin",
            body: formData
        });

        const data = await respuesta.json();

        if (!data.exito) {
            mostrarToast(data.mensaje || "No se pudo guardar el destino", "error");
            return;
        }

        mostrarToast(data.mensaje || "Destino guardado correctamente", "exito");
        cerrarModal();
        renderizarTabla();

    } catch (error) {
        console.error(error);
        mostrarToast("No se pudo conectar con el servidor", "error");
    }
}


/* =========================================================
   ELIMINAR
========================================================= */

async function eliminarDestino(id) {
    const destino = destinosCache.find(d => Number(d.id_destino) === id);

    if (!destino) return;

    const confirmar = confirm(`¿Eliminar el destino "${destino.nombre}"? Esta acción no se puede deshacer.`);

    if (!confirmar) return;

    const formData = new FormData();
    formData.append("formulario", "destino_eliminar");
    formData.append("id_destino", id);

    try {
        const respuesta = await fetch(BASE_URL, {
            method: "POST",
            credentials: "same-origin",
            body: formData
        });

        const data = await respuesta.json();

        if (!data.exito) {
            mostrarToast(data.mensaje || "No se pudo eliminar el destino", "error");
            return;
        }

        mostrarToast(data.mensaje || "Destino eliminado", "exito");
        renderizarTabla();

    } catch (error) {
        console.error(error);
        mostrarToast("No se pudo conectar con el servidor", "error");
    }
}


/* =========================================================
   TOAST DE NOTIFICACIONES
========================================================= */

let toastTimeout;

function mostrarToast(mensaje, tipo) {
    clearTimeout(toastTimeout);

    toast.textContent = mensaje;
    toast.className = `toast ${tipo}`;
    toast.style.display = "block";

    toastTimeout = setTimeout(() => {
        toast.style.display = "none";
    }, 3000);
}


/* =========================================================
   EVENTOS PRINCIPALES
========================================================= */

btnNuevoDestino.addEventListener("click", abrirModalNuevo);
btnCancelar.addEventListener("click", cerrarModal);
formDestino.addEventListener("submit", guardarDestino);

modal.addEventListener("click", (evento) => {
    if (evento.target === modal) {
        cerrarModal();
    }
});

document.addEventListener("keydown", (evento) => {
    if (evento.key === "Escape" && modal.classList.contains("abierto")) {
        cerrarModal();
    }
});


/* =========================================================
   INICIALIZAR
========================================================= */

document.addEventListener("DOMContentLoaded", renderizarTabla);