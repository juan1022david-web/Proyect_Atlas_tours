/* =========================================================
   CRUD DE VEHICULOS - Atlas Tours
========================================================= */

const API_URL = '../php/crud_vehiculos.php';

// =====================================================
// REFERENCIAS DEL DOM
// =====================================================

const tablaBody       = document.getElementById('tablaBody');
const modalVehiculo   = document.getElementById('modalVehiculo');
const tituloModal     = document.getElementById('tituloModal');
const formVehiculo    = document.getElementById('formVehiculo');

const btnNuevoVehiculo = document.getElementById('btnNuevoVehiculo');
const btnCerrarModal   = document.getElementById('btnCerrarModal');
const btnCancelar      = document.getElementById('btnCancelar');

const inputId          = document.getElementById('id_vehiculo');
const inputPlaca       = document.getElementById('placa');
const inputMarca       = document.getElementById('marca');
const inputModelo      = document.getElementById('modelo');
const inputCapacidad   = document.getElementById('capacidad');
const inputDescripcion = document.getElementById('descripcion');
const inputImagen      = document.getElementById('imagen');
const inputImagenActual = document.getElementById('imagenActual');
const previewImagen    = document.getElementById('previewImagen');
const inputEstado      = document.getElementById('estado');

const toast = document.getElementById('toast');


// =====================================================
// TOAST DE MENSAJES
// =====================================================

function mostrarToast(mensaje, tipo = 'exito') {
    toast.textContent = mensaje;
    toast.className = `toast ${tipo} mostrar`;
    toast.style.display = 'block';

    setTimeout(() => {
        toast.classList.remove('mostrar');
        toast.style.display = 'none';
    }, 3000);
}


// =====================================================
// CARGAR VEHICULOS EN LA TABLA
// =====================================================

async function cargarVehiculos() {
    try {
        const respuesta = await fetch(API_URL);
        const vehiculos = await respuesta.json();

        tablaBody.innerHTML = '';

        vehiculos.forEach(v => {
            const fila = document.createElement('tr');

            fila.innerHTML = `
                <td>${v.id_vehiculo}</td>
                <td>${v.placa}</td>
                <td>${v.marca}</td>
                <td>${v.modelo}</td>
                <td>${v.capacidad}</td>
                <td>${v.descripcion}</td>
                <td><img src="${v.imagen}" alt="${v.marca}" class="tabla-imagen"></td>
                <td>
                    <span class="estado-badge estado-${v.estado.toLowerCase()}">
                        ${v.estado}
                    </span>
                </td>
                <td>
                    <button type="button" class="btn-editar" data-id="${v.id_vehiculo}">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button type="button" class="btn-eliminar" data-id="${v.id_vehiculo}">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            `;

            fila.querySelector('.btn-editar')
                .addEventListener('click', () => abrirModalEditar(v));

            fila.querySelector('.btn-eliminar')
                .addEventListener('click', () => eliminarVehiculo(v.id_vehiculo));

            tablaBody.appendChild(fila);
        });

    } catch (error) {
        mostrarToast('Error al cargar los vehiculos', 'error');
        console.error(error);
    }
}


// =====================================================
// ABRIR MODAL - NUEVO VEHICULO
// =====================================================

function abrirModalNuevo() {
    formVehiculo.reset();

    inputId.value = '';
    inputImagenActual.value = '';
    previewImagen.src = '';
    previewImagen.style.display = 'none';

    tituloModal.textContent = 'Nuevo vehiculo';

    modalVehiculo.classList.add('abierto');
}


// =====================================================
// ABRIR MODAL - EDITAR VEHICULO
// =====================================================

function abrirModalEditar(vehiculo) {
    inputId.value          = vehiculo.id_vehiculo;
    inputPlaca.value       = vehiculo.placa;
    inputMarca.value       = vehiculo.marca;
    inputModelo.value      = vehiculo.modelo;
    inputCapacidad.value   = vehiculo.capacidad;
    inputDescripcion.value = vehiculo.descripcion;
    inputEstado.value      = vehiculo.estado;

    inputImagenActual.value = vehiculo.imagen;
    inputImagen.value       = '';

    previewImagen.src = vehiculo.imagen;
    previewImagen.style.display = 'block';

    tituloModal.textContent = 'Editar vehiculo';

    modalVehiculo.classList.add('abierto');
}


// =====================================================
// CERRAR MODAL
// =====================================================

function cerrarModal() {
    modalVehiculo.classList.remove('abierto');
}


// =====================================================
// VISTA PREVIA DE LA IMAGEN SELECCIONADA
// =====================================================

inputImagen.addEventListener('change', () => {
    const archivo = inputImagen.files[0];

    if (!archivo) return;

    const lector = new FileReader();

    lector.onload = (e) => {
        previewImagen.src = e.target.result;
        previewImagen.style.display = 'block';
    };

    lector.readAsDataURL(archivo);
});


// =====================================================
// GUARDAR (CREAR / EDITAR) VEHICULO
// =====================================================

formVehiculo.addEventListener('submit', async (e) => {
    e.preventDefault();

    const datos = new FormData();
    const id = inputId.value;

    datos.append('accion', id ? 'editar' : 'crear');

    if (id) {
        datos.append('id', id);
    }

    datos.append('placa', inputPlaca.value.trim());
    datos.append('marca', inputMarca.value.trim());
    datos.append('modelo', inputModelo.value.trim());
    datos.append('capacidad', inputCapacidad.value);
    datos.append('descripcion', inputDescripcion.value.trim());
    datos.append('estado', inputEstado.value);

    if (inputImagen.files[0]) {
        datos.append('imagen', inputImagen.files[0]);
    }

    try {
        const respuesta = await fetch(API_URL, {
            method: 'POST',
            body: datos
        });

        const resultado = await respuesta.json();

        if (!respuesta.ok) {
            mostrarToast(resultado.error || 'Ocurrió un error', 'error');
            return;
        }

        mostrarToast(resultado.mensaje, 'exito');
        cerrarModal();
        cargarVehiculos();

    } catch (error) {
        mostrarToast('Error al guardar el vehiculo', 'error');
        console.error(error);
    }
});


// =====================================================
// ELIMINAR VEHICULO
// =====================================================

async function eliminarVehiculo(id) {
    const confirmar = confirm('¿Seguro que deseas eliminar este vehiculo?');

    if (!confirmar) return;

    const datos = new FormData();
    datos.append('accion', 'eliminar');
    datos.append('id', id);

    try {
        const respuesta = await fetch(API_URL, {
            method: 'POST',
            body: datos
        });

        const resultado = await respuesta.json();

        if (!respuesta.ok) {
            mostrarToast(resultado.error || 'Ocurrió un error', 'error');
            return;
        }

        mostrarToast(resultado.mensaje, 'exito');
        cargarVehiculos();

    } catch (error) {
        mostrarToast('Error al eliminar el vehiculo', 'error');
        console.error(error);
    }
}


// =====================================================
// EVENTOS DE APERTURA / CIERRE DEL MODAL
// =====================================================

btnNuevoVehiculo.addEventListener('click', abrirModalNuevo);
btnCerrarModal.addEventListener('click', cerrarModal);
btnCancelar.addEventListener('click', cerrarModal);

modalVehiculo.addEventListener('click', (e) => {
    if (e.target === modalVehiculo) {
        cerrarModal();
    }
});


// =====================================================
// CARGA INICIAL
// =====================================================

document.addEventListener('DOMContentLoaded', cargarVehiculos);