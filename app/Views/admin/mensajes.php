<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Mensajes y Avisos</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Inicio</a></li>
                <li class="breadcrumb-item active">Mensajes</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= session()->getFlashdata('success') ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<?php
// El original muestra solo el primer registro (mysql_fetch_assoc — primer row de ORDER BY Id DESC)
$row_base = !empty($mensajes) ? $mensajes[0] : [];
?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-body">
                <form action="<?= base_url('admin/mensajes/guardar') ?>" method="POST"
                      name="mensajes" id="mensajes">
                    <?= csrf_field() ?>
                    <input type="hidden" name="Id"    value="<?= (int)($row_base['Id'] ?? 0) ?>">
                    <input type="hidden" name="fecha" value="<?= date('Y-m-d') ?>">

                    <div class="form-group">
                        <label for="t_mensaje">Título del mensaje</label>
                        <input name="t_mensaje" type="text" class="form-control" id="t_mensaje"
                               placeholder="Titulo del mensaje"
                               value="<?= esc($row_base['t_mensaje'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="texto">Mensage</label>
                        <textarea class="form-control" id="texto" name="texto" rows="7"
                                  placeholder="Escribe tu mensaje"><?= esc($row_base['texto'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="archivos_principal0">Fotografía</label><br>
                        <input type="file" name="archivos_principal[]" id="archivos_principal0"
                               onchange="seleccionado_principal('archivos_principal0');"
                               class="filestyle" data-icon="false"
                               data-classButton="btn btn-default"
                               data-classInput="form-control inline v-middle input-s">
                        <p></p>
                        <input type="hidden" readonly name="imagen_nueva_principal0"
                               id="imagen_nueva_principal0"
                               value="<?= esc($row_base['imagen'] ?? '') ?>">
                        <div name="imagen0" id="imagen0"
                             class="form-control row" style="height:auto; padding:15px;">
                            <?php if (!empty($row_base['imagen'])): ?>
                            <img src="<?= base_url(esc($row_base['imagen'])) ?>"
                                 alt="" class="img-fluid" style="max-width:400px;">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <button class="btn btn-sm btn-info" type="submit">
                            <i class="simple-icon-paper-plane push-5-r"></i> Enviar mensaje
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
function seleccionado_principal(valor) {
    var archivos = document.getElementById(valor);
    var archivo  = archivos.files;
    var orden    = valor.substring(18); // extrae "0" de "archivos_principal0"

    var data = new FormData();
    for (var i = 0; i < archivo.length; i++) {
        data.append('archivo' + i, archivo[i]);
    }
    data.append('orden', orden);
    data.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    $.ajax({
        url: '<?= base_url('admin/mensajes/subir') ?>',
        type: 'POST',
        contentType: false,
        data: data,
        processData: false,
        cache: false
    }).done(function(msg) {
        msg = (msg || '').trim();
        if (msg.indexOf('*-') === -1 || msg.substring(0, 2) === 'no') {
            alert('No se pudo guardar el archivo en el servidor.');
            return;
        }
        var msgArray    = msg.split('*-');
        var orden_nuevo = msgArray[1];
        document.getElementById('imagen_nueva_principal' + orden_nuevo).value = msgArray[0];
        $('#imagen' + orden_nuevo).html(
            '<img src="<?= base_url() ?>' + msgArray[0] + '" class="img-fluid" style="max-width:400px;"> ' +
            '<small class="text-success ml-2">Archivo cargado</small>'
        );
    }).fail(function(jqXHR) {
        console.error('subirImagenMensaje error HTTP', jqXHR.status, jqXHR.responseText);
        alert('Error al subir la imagen. (HTTP ' + jqXHR.status + ') — revisa la consola del navegador para más detalles.');
    });
}
</script>
<?= $this->endSection() ?>
