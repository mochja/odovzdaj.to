(function ($, u) {

    function load(target, $el, start) {
        $el.load(u.baseUrl + 'component/' + target + '?start=' + start);
    }

    $(function () {
        $(document).on('click', '.btn-pag-prev, .btn-pag-next', function () {

            var $this = $(this);
            var $top = $this.parent();
            var target = $top.data('pag-target');
            var start = $top.data('pag-start');

            if ($this.hasClass('btn-pag-prev')) {
                start = start * -1;
            }

            load(target, $('.' + target), start);
        });
    });
})(jQuery, window.UploadIT = window.UploadIT || {});
