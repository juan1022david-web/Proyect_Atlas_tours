/* =========================================================
   CRUD DE USUARIOS - ATLAS TOURS
========================================================= */

const API_URL = '../php/crud_usuarios.php';


// =====================================================
// REFERENCIAS DEL DOM
// =====================================================

const tablaBody = document.getElementById('tablaBody');

const modalUsuario = document.getElementById('modalUsuario');

const tituloModal = document.getElementById('tituloModal');

const formUsuario = document.getElementById('formUsuario');

const btnNuevoUsuario =
    document.getElementById('btnNuevoUsuario');

const btnCerrarModal =
    document.getElementById('btnCerrarModal');

const btnCancelar =
    document.getElementById('btnCancelar');


const inputId =
    document.getElementById('id_usuario');

const inputNombres =
    document.getElementById('nombres');

const inputApellidos =
    document.getElementById('apellidos');

const selectTipoDocumento =
    document.getElementById('id_tipo_documento');

const inputNumeroIdentificacion =
    document.getElementById('numero_identificacion');

const inputCorreo =
    document.getElementById('correo');

const inputTelefono =
    document.getElementById('telefono');

const inputContrasena =
    document.getElementById('contrasena');

const labelContrasena =
    document.getElementById('labelContrasena');

const ayudaContrasena =
    document.getElementById('ayudaContrasena');

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
// CARGAR TIPOS DE DOCUMENTO (select del formulario)
// =====================================================

async function cargarTiposDocumento() {

    try {

        const respuesta = await fetch(`${API_URL}?accion=tipos_documento`);

        const tipos = await respuesta.json();

        if (!respuesta.ok) {

            throw new Error(
                tipos.error || 'Error al cargar los tipos de documento'
            );

        }

        selectTipoDocumento.innerHTML =
            '<option value="">Seleccione...</option>';

        tipos.forEach(t => {

            const opcion = document.createElement('option');

            opcion.value = t.id_tipo_documento;

            opcion.textContent = t.tipo;

            selectTipoDocumento.appendChild(opcion);

        });

    } catch (error) {

        console.error(error);

        mostrarToast(
            error.message || 'Error al cargar los tipos de documento',
            'error'
        );

    }

}


// =====================================================
// CARGAR USUARIOS
// =====================================================

async function cargarUsuarios() {

    try {

        const respuesta = await fetch(API_URL);

        const texto = await respuesta.text();

        let usuarios;

        try {

            usuarios = JSON.parse(texto);

        } catch (error) {

            console.error('Respuesta del servidor:', texto);

            throw new Error(
                'El servidor no devolvió JSON válido.'
            );
        }


        if (!respuesta.ok) {

            throw new Error(
                usuarios.error || 'Error al cargar usuarios'
            );

        }


        tablaBody.innerHTML = '';


        if (!Array.isArray(usuarios) || usuarios.length === 0) {

            tablaBody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align:center;">
                        No hay usuarios registrados.
                    </td>
                </tr>
            `;

            return;
        }


        usuarios.forEach(u => {

            const fila = document.createElement('tr');


            fila.innerHTML = `

                <td>${u.id_usuario}</td>

                <td>${u.nombres ?? ''}</td>

                <td>${u.apellidos ?? ''}</td>

                <td>${u.tipo_documento ?? '-'}</td>

                <td>${u.numero_identificacion ?? ''}</td>

                <td>${u.correo ?? ''}</td>

                <td>${u.telefono ?? '-'}</td>

                <td>${u.creado_en ?? '-'}</td>

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
                    () => abrirModalEditar(u)
                );


            fila
                .querySelector('.btn-eliminar')
                .addEventListener(
                    'click',
                    () => eliminarUsuario(u.id_usuario)
                );


            tablaBody.appendChild(fila);

        });


    } catch (error) {

        console.error(error);

        mostrarToast(
            error.message || 'Error al cargar los usuarios',
            'error'
        );

    }

}


// =====================================================
// ABRIR MODAL NUEVO
// =====================================================

function abrirModalNuevo() {

    formUsuario.reset();

    inputId.value = '';


    tituloModal.textContent =
        'Nuevo Usuario';


    inputContrasena.required = true;

    labelContrasena.innerHTML =
        '<i class="fa-solid fa-lock"></i> Contraseña';

    ayudaContrasena.textContent = '';


    modalUsuario.classList.add('abierto');

}


// =====================================================
// ABRIR MODAL EDITAR
// =====================================================

function abrirModalEditar(usuario) {

    inputId.value =
        usuario.id_usuario;

    inputNombres.value =
        usuario.nombres ?? '';

    inputApellidos.value =
        usuario.apellidos ?? '';

    selectTipoDocumento.value =
        usuario.id_tipo_documento ?? '';

    inputNumeroIdentificacion.value =
        usuario.numero_identificacion ?? '';

    inputCorreo.value =
        usuario.correo ?? '';

    inputTelefono.value =
        usuario.telefono ?? '';


    inputContrasena.value = '';

    inputContrasena.required = false;

    labelContrasena.innerHTML =
        '<i class="fa-solid fa-lock"></i> Contraseña';

    ayudaContrasena.textContent =
        'Déjala vacía para no cambiarla.';


    tituloModal.textContent =
        'Editar Usuario';


    modalUsuario.classList.add('abierto');

}


// =====================================================
// CERRAR MODAL
// =====================================================

function cerrarModal() {

    modalUsuario.classList.remove('abierto');

}


// =====================================================
// GUARDAR USUARIO
// =====================================================

formUsuario.addEventListener(
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
            'nombres',
            inputNombres.value.trim()
        );


        datos.append(
            'apellidos',
            inputApellidos.value.trim()
        );


        datos.append(
            'id_tipo_documento',
            selectTipoDocumento.value
        );


        datos.append(
            'numero_identificacion',
            inputNumeroIdentificacion.value.trim()
        );


        datos.append(
            'correo',
            inputCorreo.value.trim()
        );


        datos.append(
            'telefono',
            inputTelefono.value.trim()
        );


        if (inputContrasena.value.trim() !== '') {

            datos.append(
                'contrasena',
                inputContrasena.value.trim()
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


            await cargarUsuarios();


        } catch (error) {

            console.error(error);

            mostrarToast(
                error.message ||
                'Error al guardar el usuario',
                'error'
            );

        }

    }
);


// =====================================================
// ELIMINAR USUARIO
// =====================================================

async function eliminarUsuario(id) {

    const confirmar =
        confirm(
            '¿Seguro que deseas eliminar este usuario?'
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
            'Usuario eliminado correctamente',
            'exito'
        );


        await cargarUsuarios();


    } catch (error) {

        console.error(error);

        mostrarToast(
            'Error al eliminar el usuario',
            'error'
        );

    }

}


// =====================================================
// EVENTOS
// =====================================================

btnNuevoUsuario.addEventListener(
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


modalUsuario.addEventListener(
    'click',
    (e) => {

        if (e.target === modalUsuario) {

            cerrarModal();

        }

    }
);


// =====================================================
// CARGA INICIAL
// =====================================================

cargarTiposDocumento();

cargarUsuarios();