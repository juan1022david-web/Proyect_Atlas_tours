const API_URL = "php/Base_De_Datos.php?accion=listar_destinos";

const IMAGEN_PLACEHOLDER = "data:image/svg+xml;base64," + btoa(`
<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200" viewBox="0 0 300 200">
    <rect width="300" height="200" fill="#e5e7eb"/>
    <text x="150" y="105" font-family="Arial, sans-serif" font-size="14" fill="#6b7280" text-anchor="middle">Sin imagen</text>
</svg>
`);

document.addEventListener("DOMContentLoaded", cargarDestinosPublicos);

async function cargarDestinosPublicos() {
    const contenedor = document.getElementById("contenedorDestinos");
    if (!contenedor) return;

    try {
        const respuesta = await fetch(API_URL);
        const data = await respuesta.json();

        if (!data.exito || !data.destinos || data.destinos.length === 0) {
            contenedor.innerHTML = "<p style='text-align:center; width:100%; color:#6b7280;'>No hay destinos disponibles.</p>";
            return;
        }

        const destinosActivos = data.destinos.filter(destino => destino.estado === "Activo");

        if (destinosActivos.length === 0) {
            contenedor.innerHTML = "<p style='text-align:center; width:100%; color:#6b7280;'>No hay destinos activos.</p>";
            return;
        }

        contenedor.innerHTML = "";

        destinosActivos.forEach(destino => {
            let rutaImagen = destino.imagen;

            if (rutaImagen) {
                // Elimina diagonales al inicio para construir la ruta relativa limpia desde la raíz
                rutaImagen = rutaImagen.replace(/^\/+/, '');
            } else {
                rutaImagen = IMAGEN_PLACEHOLDER;
            }

            const elemento = document.createElement("div");
            elemento.className = "elemento";

            elemento.innerHTML = `
                <div class="contenedor-imagen">
                    <img 
                        src="${rutaImagen}" 
                        alt="${destino.nombre}"
                        onerror="this.onerror=null; this.src='${IMAGEN_PLACEHOLDER}';"
                    >
                </div>
                <div class="titulo">
                    <h3>${destino.nombre.toUpperCase()}</h3>
                    <p><i class="fa-solid fa-phone"></i> ${destino.telefono || "Contacto no disponible"}</p>
                    <button class="open-modal" data-modal="${destino.id_destino}">
                        Saber más
                    </button>
                </div>
            `;

            contenedor.appendChild(elemento);
        });

    } catch (error) {
        console.error("Error al obtener destinos:", error);
        contenedor.innerHTML = "<p style='text-align:center; width:100%; color:#b91c1c;'>Error al cargar los destinos.</p>";
    }
}