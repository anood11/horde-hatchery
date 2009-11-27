/**
 * Provides the javascript for the login.php script.
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

var ImpLogin = {
    // The following variables are defined in login.php:
    //  dimp_sel, server_key_error

    submit: function(parentfunc)
    {
        if ($('imp_server_key') && $F('imp_server_key').startsWith('_')) {
            alert(this.server_key_error);
            $('imp_server_key').focus();
            return;
        }

        parentfunc();
    },

    onDomLoad: function()
    {
        /* Activate dynamic view. */
        var s = $('imp_select_view'),
            o = s.down('option[value=dimp]').show();
        if (this.dimp_sel) {
            s.selectedIndex = o.index;
        }
    }
};

HordeLogin.submit = HordeLogin.submit.wrap(ImpLogin.submit.bind(ImpLogin));
document.observe('dom:loaded', ImpLogin.onDomLoad.bind(ImpLogin));
