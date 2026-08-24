/* =========================================================
   CRUD DE VEHÍCULOS - ATLAS TOURS
========================================================= */

const API_URL = '../php/crud_vehiculos.php';


// =====================================================
// REFERENCIAS DEL DOM
// =====================================================

const tablaBody = document.getElementById('tablaBody');

const modalVehiculo = document.getElementById('modalVehiculo');

const tituloModal = document.getElementById('tituloModal');

const formVehiculo = document.getElementById('formVehiculo');

const btnNuevoVehiculo =
    document.getElementById('btnNuevoVehiculo');

const btnCerrarModal =
    document.getElementById('btnCerrarModal');

const btnCancelar =
    document.getElementById('btnCancelar');


const inputId =
    document.getElementById('id_vehiculo');

const inputPlaca =
    document.getElementById('placa');

const inputMarca =
    document.getElementById('marca');

const inputModelo =
    document.getElementById('modelo');

const inputCapacidad =
    document.getElementById('capacidad');

const inputDescripcion =
    document.getElementById('descripcion');

const inputImagen =
    document.getElementById('imagen');

const inputImagenActual =
    document.getElementById('imagenActual');

const previewImagen =
    document.getElementById('previewImagen');

const inputEstado =
    document.getElementById('estado');

const toast =
    document.getElementById('toast');


// =====================================================
// TOAST
// =====================================================

function mostrarToast(mensaje, tipo = 'exito') {

    if (!toast) return;

    toast.textContent = mensaje;

    toast.className = `toast ${tipo} mostrar`;

    toast.style.display = 'block';

    setTimeout(() => {

        toast.classList.remove('mostrar');

        toast.style.display = 'none';

    }, 3000);
}


// =====================================================
// CARGAR VEHÍCULOS
// =====================================================

async function cargarVehiculos() {

    try {

        const respuesta = await fetch(API_URL);

        const texto = await respuesta.text();

        let vehiculos;

        try {

            vehiculos = JSON.parse(texto);

        } catch (error) {

            console.error('Respuesta del servidor:', texto);

            throw new Error(
                'El servidor no devolvió JSON válido.'
            );
        }


        if (!respuesta.ok) {

            throw new Error(
                vehiculos.error || 'Error al cargar vehículos'
            );

        }


        tablaBody.innerHTML = '';


        if (!Array.isArray(vehiculos) || vehiculos.length === 0) {

            tablaBody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align:center;">
                        No hay vehículos registrados.
                    </td>
                </tr>
            `;

            return;
        }


        vehiculos.forEach(v => {

            const fila = document.createElement('tr');


            fila.innerHTML = `

                <td>${v.id_vehiculo}</td>

                <td>${v.placa ?? ''}</td>

                <td>${v.marca ?? ''}</td>

                <td>${v.modelo ?? ''}</td>

                <td>${v.capacidad ?? ''}</td>

                <td>${v.descripcion ?? ''}</td>

                <td>

                    ${
                        v.imagen
                        ?
                        `<img
                            src="${v.imagen}"
                            alt="${v.marca ?? 'Vehículo'}"
                            class="tabla-imagen"
                            onerror="this.style.display='none';">`
                        :
                        'Sin imagen'
                    }

                </td>

                <td>

                    <span class="
                        estado-badge
                        estado-${String(v.estado).toLowerCase()}
                    ">

                        ${v.estado ?? ''}

                    </span>

                </td>

                <td>

                    <button
                        type="button"
                        class="btn-editar"
                        title="Editar">

                        <i class="fa-solid fa-pen"></i>

                    </button>


                    <button
                        type="button"
                        class="btn-eliminar"
                        title="Eliminar">

                        <i class="fa-solid fa-trash"></i>

                    </button>

                </td>
            `;


            fila
                .querySelector('.btn-editar')
                .addEventListener(
                    'click',
                    () => abrirModalEditar(v)
                );


            fila
                .querySelector('.btn-eliminar')
                .addEventListener(
                    'click',
                    () => eliminarVehiculo(v.id_vehiculo)
                );


            tablaBody.appendChild(fila);

        });


    } catch (error) {

        console.error(error);

        mostrarToast(
            error.message || 'Error al cargar los vehículos',
            'error'
        );

    }

}


// =====================================================
// ABRIR MODAL NUEVO
// =====================================================

function abrirModalNuevo() {

    formVehiculo.reset();

    inputId.value = '';

    inputImagenActual.value = '';

    previewImagen.src = '';

    previewImagen.style.display = 'none';


    inputEstado.value = 'Activo';


    tituloModal.textContent =
        'Nuevo Vehículo';


    modalVehiculo.classList.add('abierto');

}


// =====================================================
// ABRIR MODAL EDITAR
// =====================================================

function abrirModalEditar(vehiculo) {

    inputId.value =
        vehiculo.id_vehiculo;

    inputPlaca.value =
        vehiculo.placa ?? '';

    inputMarca.value =
        vehiculo.marca ?? '';

    inputModelo.value =
        vehiculo.modelo ?? '';

    inputCapacidad.value =
        vehiculo.capacidad ?? '';

    inputDescripcion.value =
        vehiculo.descripcion ?? '';

    inputEstado.value =
        vehiculo.estado ?? 'Activo';


    inputImagenActual.value =
        vehiculo.imagen ?? '';


    inputImagen.value = '';


    if (vehiculo.imagen) {

        previewImagen.src =
            vehiculo.imagen;

        previewImagen.style.display =
            'block';

    } else {

        previewImagen.src = '';

        previewImagen.style.display =
            'none';

    }


    tituloModal.textContent =
        'Editar Vehículo';


    modalVehiculo.classList.add('abierto');

}


// =====================================================
// CERRAR MODAL
// =====================================================

function cerrarModal() {

    modalVehiculo.classList.remove('abierto');

}


// =====================================================
// VISTA PREVIA DE IMAGEN
// =====================================================

inputImagen.addEventListener(
    'change',
    () => {

        const archivo =
            inputImagen.files[0];


        if (!archivo) return;


        const lector =
            new FileReader();


        lector.onload =
            (e) => {

                previewImagen.src =
                    e.target.result;

                previewImagen.style.display =
                    'block';

            };


        lector.readAsDataURL(archivo);

    }
);


// =====================================================
// GUARDAR VEHÍCULO
// =====================================================

formVehiculo.addEventListener(
    'submit',
    async (e) => {

        e.preventDefault();


        const id =
            inputId.value.trim();


        const datos =
            new FormData();


        datos.append(
            'accion',
            id ? 'editar' : 'crear'
        );


        if (id) {

            datos.append(
                'id',
                id
            );

        }


        datos.append(
            'placa',
            inputPlaca.value.trim()
        );


        datos.append(
            'marca',
            inputMarca.value.trim()
        );


        datos.append(
            'modelo',
            inputModelo.value.trim()
        );


        datos.append(
            'capacidad',
            inputCapacidad.value
        );


        datos.append(
            'descripcion',
            inputDescripcion.value.trim()
        );


        datos.append(
            'estado',
            inputEstado.value
        );


        if (inputImagen.files.length > 0) {

            datos.append(
                'imagen',
                inputImagen.files[0]
            );

        }


        try {

            const respuesta =
                await fetch(
                    API_URL,
                    {
                        method: 'POST',
                        body: datos
                    }
                );


            const texto =
                await respuesta.text();


            let resultado;


            try {

                resultado =
                    JSON.parse(texto);

            } catch (error) {

                console.error(
                    'Respuesta PHP:',
                    texto
                );

                throw new Error(
                    'PHP no devolvió una respuesta JSON válida.'
                );

            }


            if (!respuesta.ok) {

                mostrarToast(
                    resultado.error ||
                    'Ocurrió un error',
                    'error'
                );

                return;

            }


            mostrarToast(
                resultado.mensaje ||
                'Operación realizada correctamente',
                'exito'
            );


            cerrarModal();


            await cargarVehiculos();


        } catch (error) {

            console.error(error);

            mostrarToast(
                error.message ||
                'Error al guardar el vehículo',
                'error'
            );

        }

    }
);


// =====================================================
// ELIMINAR VEHÍCULO
// =====================================================

async function eliminarVehiculo(id) {

    const confirmar =
        confirm(
            '¿Seguro que deseas eliminar este vehículo?'
        );


    if (!confirmar) return;


    const datos =
        new FormData();


    datos.append(
        'accion',
        'eliminar'
    );


    datos.append(
        'id',
        id
    );


    try {

        const respuesta =
            await fetch(
                API_URL,
                {
                    method: 'POST',
                    body: datos
                }
            );


        const resultado =
            await respuesta.json();


        if (!respuesta.ok) {

            mostrarToast(
                resultado.error ||
                'Ocurrió un error',
                'error'
            );

            return;

        }


        mostrarToast(
            resultado.mensaje ||
            'Vehículo eliminado correctamente',
            'exito'
        );


        await cargarVehiculos();


    } catch (error) {

        console.error(error);

        mostrarToast(
            'Error al eliminar el vehículo',
            'error'
        );

    }

}


// =====================================================
// EVENTOS
// =====================================================

btnNuevoVehiculo.addEventListener(
    'click',
    abrirModalNuevo
);


btnCerrarModal.addEventListener(
    'click',
    cerrarModal
);


btnCancelar.addEventListener(
    'click',
    cerrarModal
);


modalVehiculo.addEventListener(
    'click',
    (e) => {

        if (e.target === modalVehiculo) {

            cerrarModal();

        }

    }
);


// =====================================================
// CARGA INICIAL
// =====================================================

cargarVehiculos();