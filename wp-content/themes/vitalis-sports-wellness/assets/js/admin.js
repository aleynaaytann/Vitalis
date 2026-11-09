jQuery(function ($) {
    var frame;
    var $ids = $('#vitalis-gallery-ids');
    var $preview = $('#vitalis-gallery-preview');
    $('#vitalis-gallery-select').on('click', function (event) {
        event.preventDefault();
        if (frame) { frame.open(); return; }
        frame = wp.media({
            title: 'Tesis galerisi için fotoğrafları seçin',
            button: { text: 'Galeriye ekle' },
            library: { type: 'image' },
            multiple: true
        });
        frame.on('open', function () {
            var selection = frame.state().get('selection');
            var ids = ($ids.val() || '').split(',').filter(Boolean);
            ids.forEach(function (id) { var attachment = wp.media.attachment(id); attachment.fetch(); selection.add(attachment); });
        });
        frame.on('select', function () {
            var selection = frame.state().get('selection');
            var ids = [];
            $preview.empty();
            selection.each(function (attachment) {
                var data = attachment.toJSON(); ids.push(data.id);
                var thumb = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;
                $('<img>', { src: thumb, alt: data.alt || '', css: { width: '72px', height: '72px', objectFit: 'cover', margin: '8px 8px 0 0' } }).appendTo($preview);
            });
            $ids.val(ids.join(','));
        });
        frame.open();
    });
    $('#vitalis-gallery-clear').on('click', function (event) { event.preventDefault(); $ids.val(''); $preview.empty(); });

    var certificateFrame;
    $('#vitalis-certificate-select').on('click', function (event) {
        event.preventDefault();
        var $field = $('textarea[name="vitalis_meta[certificates]"]');
        if (certificateFrame) { certificateFrame.open(); return; }
        certificateFrame = wp.media({
            title: 'Antrenör sertifika veya eğitim belgesi seçin',
            button: { text: 'Belge bağlantısını ekle' },
            library: { type: 'application' },
            multiple: false
        });
        certificateFrame.on('select', function () {
            var item = certificateFrame.state().get('selection').first().toJSON();
            var line = (item.title || item.filename || 'Eğitim belgesi') + ' | ' + item.url;
            var current = ($field.val() || '').trim();
            $field.val(current ? current + '\n' + line : line);
        });
        certificateFrame.open();
    });
});
