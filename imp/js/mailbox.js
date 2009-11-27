/**
 * Provides the javascript for the mailbox.php script (standard view).
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

var ImpMailbox = {
    // The following variables are defined in mailbox.php:
    //  sortlimit, unread

    anySelected: function()
    {
        return $('messages').select('[name="indices[]"]').detect(Form.Element.getValue);
    },

    selectRow: function(id, select)
    {
        [ id ].invoke(select ? 'addClassName' : 'removeClassName', 'selectedRow');
        id.down('INPUT.checkbox').setValue(select);
    },

    confirmDialog: function(url, msg)
    {
        RedBox.overlay = true;
        RedBox.showHtml('<div id="RB_confirm"><p>' + msg + '</p><input type="button" class="button" onclick="window.location=\'' + url + '\';" value="' + IMP.text.yes + '" />' +
                        '<input type="button" class="button" onclick="RedBox.close();" value="' + IMP.text.no + '" /></div>');
    },

    submit: function(actID)
    {
        if (!this.anySelected()) {
            alert(IMP.text.mailbox_submit);
            return;
        }

        switch (actID) {
        case 'delete_messages':
            if (IMP.conf.pop3 && !confirm(IMP.text.mailbox_delete)) {
                return;
            }
            break;

        case 'spam_report':
            if (!confirm(IMP.text.spam_report)) {
                return;
            }
            break;

        case 'nostpam_report':
            if (!confirm(IMP.text.notspam_report)) {
                return;
            }
            break;
        }

        $('actionID').setValue(actID);
        $('messages').submit();
    },

    selectRange: function(e)
    {
        // elt = checkbox element
        var elt = e.element(),
            tr = elt.up('TR'),
            checked = $F(elt),
            end, start;

        if (this.startrange && e.shiftKey) {
            if (this.startrange != elt) {
                // Dirty trick - use position in page to determine which way
                // to traverse
                if (this.startrange.offsetTop < tr.offsetTop) {
                    start = this.startrange.next();
                    end = tr;
                } else {
                    start = tr;
                    end = this.startrange.previous();
                }

                do {
                    this.selectRow(start, checked);
                    if (start == end) {
                        break;
                    }
                    start = start.next();
                } while (start);
            }
        } else {
            this.selectRow(tr, checked);
        }

        this.startrange = tr;
    },

    updateFolders: function(form)
    {
        var tm1 = $('targetMailbox1'),
            tm2 = $('targetMailbox2');

        if (tm2) {
            if ((form == 1 && $F(tm1) != "") ||
                (form == 2 && $F(tm2) != "")) {
                tm1.selectedIndex = tm2.selectedIndex = (form == 1)
                    ? tm1.selectedIndex
                    : tm2.selectedIndex;
            }
        }
    },

    _transfer: function(actID)
    {
        var newFolder, tmbox;

        if (this.anySelected()) {
            tmbox = $('targetMbox');
            tmbox.setValue($F('targetMailbox1'));

            // Check for a mailbox actually being selected.
            if ($F(tmbox) == '*new*') {
                newFolder = prompt(IMP.text.newfolder, '');
                if (newFolder != null && newFolder != '') {
                    $('newMbox').setValue(1);
                    tmbox.setValue(newFolder);
                    this.submit(actID);
                }
            } else {
                if ($F(tmbox) == '') {
                    alert(IMP.text.target_mbox);
                } else {
                    this.submit(actID);
                }
            }
        } else {
            alert(IMP.text.mailbox_selectone);
        }
    },

    flagMessages: function(form)
    {
        var f1 = $('flag1'), f2 = $('flag2');

        if ((form == 1 && $F(f1) != "") ||
            (form == 2 && $F(f2) != "")) {
            if (this.anySelected()) {
                $('messages').down('[name=flag]').setValue((form == 1) ? $F(f1) : $F(f2));
                this.submit('flag_messages');
            } else {
                if (form == 1) {
                    f1.selectedIndex = 0;
                } else {
                    f2.selectedIndex = 0;
                }
                alert(IMP.text.mailbox_selectone);
            }
        }
    },

    getMessage: function(id, offset)
    {
        if (!offset) {
            return id;
        }

        var mlist = $H(this.messagelist).keys(),
            i = mlist.indexOf(id),
            j = i + offset;

        if (i != -1) {
            if (j >= 0 && j < mlist.length) {
                return mlist[j];
            }
        }

        return '';
    },

    changeHandler: function(e)
    {
        var id = e.element().readAttribute('id');

        if (id) {
            if (id.startsWith('flag')) {
                this.flagMessages(id.substring(4));
            } else if (id.startsWith('targetMailbox')) {
                this.updateFolders(id.substring(13));
            }
        }
    },

    clickHandler: function(e)
    {
        if (e.isRightClick()) {
            return;
        }

        var elt = e.element(), id;

        while (Object.isElement(elt)) {
            if (elt.match('.msgactions A.widget')) {
                if (elt.hasClassName('moveAction')) {
                    this._transfer('move_messages');
                } else if (elt.hasClassName('copyAction')) {
                    this._transfer('copy_messages');
                } else if (elt.hasClassName('permdeleteAction')) {
                    if (confirm(IMP.text.mailbox_delete)) {
                        this.submit('delete_messages');
                    }
                } else if (elt.hasClassName('deleteAction')) {
                    this.submit('delete_messages');
                } else if (elt.hasClassName('undeleteAction')) {
                    this.submit('undelete_messages');
                } else if (elt.hasClassName('blacklistAction')) {
                    this.submit('blacklist');
                } else if (elt.hasClassName('whitelistAction')) {
                    this.submit('whitelist');
                } else if (elt.hasClassName('forwardAction')) {
                    this.submit('fwd_digest');
                } else if (elt.hasClassName('spamAction')) {
                    this.submit('spam_report');
                } else if (elt.hasClassName('notspamAction')) {
                    this.submit('notspam_report');
                } else if (elt.hasClassName('viewAction')) {
                    this.submit('view_messages');
                } else if (elt.hasClassName('hideAction') || elt.hasClassName('purgeAction')) {
                    return;
                }

                e.stop();
                return;
            } else if (elt.hasClassName('checkbox')) {
                this.selectRange(e);
                // Fall through to elt.up() call below.
            }

            id = elt.readAttribute('id');
            if (!id) {
                elt = elt.up();
                continue;
            }

            switch (id) {
            case 'checkheader':
            case 'checkAll':
                if (id == 'checkheader') {
                    $('checkAll').checked = !$('checkAll').checked;
                }

                $('messages').select('TABLE.messageList TR[id]').each(function(i, s) {
                    this.selectRow(i, $F('checkAll'));
                }, this);
                return;
            }

            if (!this.sortlimit &&
                elt.match('TH') &&
                elt.up('TABLE.messageList')) {
                document.location.href = elt.down('A').href;
            }

            elt = elt.up();
        }
    },

    keyDownHandler: function(e)
    {
        var elt = e.element(),
            key = e.keyCode,
            loc, search, tmp;

        if (e.altKey || e.ctrlKey) {
            if (!(key == Event.KEY_UP || key == Event.KEY_DOWN)) {
                return;
            }

            if (!this.cursor) {
                this.cursor = elt.up('TABLE.messageList TR');
            }

            if (this.cursor) {
                if (e.altKey) {
                    this.selectRow(this.cursor, !$F(this.cursor.down('INPUT.checkbox')));
                }

                switch (key) {
                case Event.KEY_UP:
                    this.cursor = this.cursor.previous();
                    if (!this.cursor.readAttribute('id')) {
                        search = 'last';
                    }
                    break;

                case Event.KEY_DOWN:
                    this.cursor = this.cursor.next();
                    if (!this.cursor) {
                        search = 'first';
                    }
                    break;
                }
            } else {
                search = key == Event.KEY_DOWN ? 'first' : 'last';
            }

            if (search) {
                tmp = $('messages').select('TABLE.messageList TR[id]');
                this.cursor = (search == 'first') ? tmp.first() : tmp.last();
            }

            this.cursor.down('TD A.mboxSubject').focus();
        } else if (key == 32 && this.cursor) {
            this.selectRow(this.cursor, !$F(this.cursor.down('INPUT.checkbox')));
        } else if (!e.shiftKey) {
            if (key == Event.KEY_LEFT && $('prev')) {
                loc = $('prev');
            } else if (key == Event.KEY_RIGHT && $('next')) {
                loc = $('next');
            }

            if (loc) {
                document.location.href = loc.readAttribute('href');
            }
            return;
        } else {
            return;
        }

        e.stop();
    },

    submitHandler: function(e)
    {
        if (e.element().readAttribute('id').startsWith('select')) {
            e.stop();
        }
    }

};

document.observe('dom:loaded', function() {
    var im = ImpMailbox;

    document.observe('change', im.changeHandler.bindAsEventListener(im));
    document.observe('click', im.clickHandler.bindAsEventListener(im));
    document.observe('keydown', im.keyDownHandler.bindAsEventListener(im));
    document.observe('submit', im.submitHandler.bindAsEventListener(im));

    if (window.fluid) {
        try {
            window.fluid.setDockBadge(ImpMailbox.unread);
        } catch (e) {}
    }
});
