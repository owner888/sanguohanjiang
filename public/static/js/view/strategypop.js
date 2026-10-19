/* 攻略 */
(function() {
    strategyPopClass = function() {
        this.e;
        this.isshow = false;
    };
    strategyPopClass.prototype = {
        init: function(params) {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            //解决因为delete params.c的问题
            var param_c = params.c;

            var initRender = function(res) {
                if (res.status == 1) {
                    that.showlist(param_c, params, res.list);
                } else {
                    xGame.toast.show(lang('checkpoint_have_no_raiders'));
                    $('#worldloading').hide();
                }
            };

            //params 来自外部
            loadContents(params, initRender, true);
            initRender = null;

        },

        showlist: function(param_c, params, list) {
            var that = this;

            //进入页面
            that.e = xGame.loadPopTmpl('strategy');
            //关闭按钮
            that.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                strategypop.hide();
                e.preventDefault();
                //2013-7-5
            });

            that.e.find('#emenyname').html(params.name);

            var html = '';
            for (var i in list) {
                html += '<li>';
                if (typeof list[i].tree != 'undefined' && list[i].tree > 1) {
                    html += '<b class="name" uid="' + list[i].uid + '">' + list[i].nickname + '<em class="tree' + list[i].tree + '"></em></b>';
                } else {
                    html += '<b class="name" uid="' + list[i].uid + '">' + list[i].nickname + '</b>';
                }
                html += '<b class="level">Lv.' + list[i].level + '</b>';
                html += '<button reportid="' + list[i].id + '" class="btn pop_btn_small">' + lang('watch') + '</button>';
                html += '</li>';
            }

            that.e.find('#strategy_list').html(html);

            var viewRender = function(type, reportid) {
                if (type == 'map') {
                    miscpop.viewBattleFromMap(reportid);
                } else if (type == 'island') {
                    miscpop.viewBattleFromTreasure(reportid);
                } else if (type == 'tower') {
                    miscpop.viewBattleFromTower(reportid);
                } else if (type == 'generaltask') {
                    miscpop.viewBattleFromGeneraltask(reportid, generaltaskWorld.generalid, generaltaskWorld.page);
                } else if (type == 'sanctum') {
                    miscpop.viewBattleFromSanctum(reportid);
                } else if (type == 'heaven') {
                    miscpop.viewBattleFromHeaven(reportid);
                } else if (type == 'copies') {
                    miscpop.viewBattleFromPacify(reportid);
                }
            };
            that.e.find('#strategy_list').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                var reportid = parseInt($(this).attr('reportid'));
                if (reportid > 0) {
                    viewRender(param_c, reportid);
                }
            });

            /* 绑定资料页面 */
            that.e.find('#strategy_list').undelegate('.name', 'touchend').delegate('.name', 'touchend',
            function() {
                var uid = parseInt($(this).attr('uid'));
                if (uid > 0) {
                    xGame.showpop('info', uid);
                }
            });

            that.e.show();
            $('#worldloading').hide();
        },

        unbindAll: function () {
	    if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.find('#strategy_list').undelegate('.pop_btn_small', 'touchend');
                this.e.find('#strategy_list').undelegate('.name', 'touchend');
		this.e.remove();
                this.e = null;
	    }
        },

        hide: function() {
            xGame.unlock();
            this.unbindAll();
            //界面隐藏
            this.isshow = false;
           
        },
    };
})();