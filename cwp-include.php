// Ensure the plugin appears in the CWP admin menu
<script type="text/javascript">
    $(document).ready(function() {
        var newButtons = '' +
            '<li>' +
            '  <a href="?module=imh-php-extension"><span aria-hidden="true" class="icon16 icomoon-icon-hammer"></span>PHP Extensions</a>' +
            '</li>';
        $(".mainnav > ul").append(newButtons);
    });
</script>