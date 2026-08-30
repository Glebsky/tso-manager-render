var ExtendedSpecs = (function () {
    const SCRIPT_CONST = {
        PREFIX: 'ES',
        NAME: loca.GetText("QUL", "MiadTropicalSunQ2") + ', ' + loca.GetText("ACL", "SellGoods_1"),
    };
    var buildTemplates;

    var UIMap = {
        ids: {
            modal: SCRIPT_CONST.PREFIX + '_ExtendedSpecsModal',
            modalData: SCRIPT_CONST.PREFIX + '_ExtendedSpecsModalData',
        },

        classes: {

        }
    };



    function openModal() {
        try {
            if (!game.gi.isOnHomzone()) {
                game.showAlert(getText('not_home'));
                return;
            }

            var state = SettingsService.getState();
            $("div[role='dialog']:not(#" + UIMap.ids.modal + "):visible").modal("hide");
            // if (!state.modalInitialized) $('#' + UIMap.ids.modal).remove();
            createModalWindow(UIMap.ids.modal, SCRIPT_CONST.NAME);

            buildTemplates = new SaveLoadTemplate('ml', function (data, name) {
                $("#" + UIMap.ids.modal + " .templateFile").html("{0} ({1}: {2})".format('&nbsp;'.repeat(5), loca.GetText("LAB", "AvatarCurrentSelection"), name));
                var state = SettingsService.getState()
                state = data;
                SettingsService.setState(state);
                SettingsService.saveSettings();
                UIRenderer.renderBody();
            });

            SettingsService.loadSettings()
            $('#' + UIMap.ids.modal + ':not(:visible)').modal({backdrop: "static"});
        } catch (e) {
            debug(e);
        }
    }

    function init() {
        try {
            SettingsService.loadSettings();
            addToolsMenuItem(SCRIPT_CONST.NAME, openModal);
        } catch (e) {
            debug(e);
        }
    }

    var SettingsService = (function () {
        var STATE = initStateData();

        function initStateData() {
            return {
                modalInitialized: false,
                data: {}
            }
        }

        function resetState() {
            STATE = initStateData();
            return STATE;
        }

        function loadSettings(){
            $.extend(STATE.data, settings.read(null, SCRIPT_CONST.PREFIX + '_SETTINGS'));
        }

        function saveSettings() {
            settings.settings[SCRIPT_CONST.PREFIX + '_SETTINGS'] = {};
            settings.store(STATE.data, SCRIPT_CONST.PREFIX + '_SETTINGS');
        }


        function getState() {
            return STATE;
        }

        function setState(state) {
            STATE = state;
        }

        return {
            getState: getState,
            setState: setState,
            loadSettings: loadSettings,
            saveSettings: saveSettings,
            resetState: resetState,
        };
    })();

    return {
        init: init,
    };
})();
ExtendedSpecs.init();