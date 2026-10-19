/* 国家宴会 */
(function() {
    countrybanquetPopClass = function() {
        this.e;
        this.isshow = false;
        this.listDom;//左边宴会列表
        this.menuDom;//右边菜单
        this.detailDom;//右边宴会参与者详情
        this.myScroller;//队伍列表滑动
        this.memberScroller;//队伍人员列表滑动
        this.isrefresh = true;
        this.refreshtimer = null;
    };

    countrybanquetPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            this.isrefresh = true;

            var initRender = function(res) {
                if (res.status == 1) {
                    that.initFirst(res);
                } else {
                    var error_msg = '';
                    switch(res.status){
                        case -1:
                            error_msg = lang('get_user_info_fail');
                            break;
                        case -2:
                            error_msg = lang('get_country_info_fail');
                            break;
                        case 2:
                            error_msg = lang('countrybanquet_not_open');
                            break;
                        case 3:
                            error_msg = lang('countrybanquet_enter_level');
                            break;
                        default:
                            error_msg = lang('undefined_error');
                            break;
                    }
                    xGame.toast.show(error_msg);
                    $('#worldloading').hide();
                }
            };

            loadContents({
                'c': 'banquet',
                'm': 'index'
            },
            initRender, true);
            initRender = null;
        },

        /* 初始化界面 */
        initFirst: function(res) {

            var that = this;

            if (that.e != null) {
                return false;
            }

            that.e = xGame.loadPopTmpl('countrybanquet');
            that.listDom = that.e.find('#countrybanquetpop_list');
            that.menuDom = that.e.find('#countrybanquetpop_menu');
            that.detailDom = that.e.find('#countrybanquet_detail');
            that.myScroller = null;
            that.memberScroller = null;

            //绑定关闭事件
            that.e.find('#hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                countrybanquetpop.hide();
                e.preventDefault();
                e.stopPropagation();

            });

            //滑动
            that.iscrollFunc();
            //更新界面次数
            that.showTimes(res.info.times);
            //霸王宴价格
            that.showPrice(res.price);
            //队伍列表
            that.fillList(res.list);
            //宴会列表
            that.fillMenu(res.banquet);
            //判断用户状态
            if(res.info.status == 1) {
                //显示队伍信息
                that.fillMeal(res.team);
                that.menuDom.hide();
            } else if(res.info.status == 2) {
                //提示队伍已解散
                xGame.toast.show(lang('countrybanquet_end'));
                //显示宴会列表
                that.menuDom.show();//宴会信息
                that.detailDom.hide();//队伍信息
            } else if(res.info.status == 3) {
                //提示上个队伍已完成
                xGame.confirm.show(lang('countrybanquet'), lang('countrybanquet_complete',[res.add_silver,res.add_contribute]), 1, function() {
                    xGame.confirm.hide();
                });
                player.updateinfo({'silver':parseInt(res.all_silver)});
                //显示宴会列表
                that.menuDom.show();//宴会信息
                that.detailDom.hide();//队伍信息
            } else {
                //显示宴会列表
                that.menuDom.show();//宴会信息
                that.detailDom.hide();//队伍信息
            }

            that.bindAll();

            that.e.show();

            //开始定时刷新
            that.refreshStart();

            $('#worldloading').hide();
            that.isshow = true;
        },

        /*更新次数*/
        showTimes: function(times) {
            this.e.find('#countrybanquet_times').html(times);
        },

        /*更新价格*/
        showPrice: function(price) {
            this.detailDom.find('#countrybanquet_gold').html('x'+ price);
        },

        //填充进行中的宴会列表
        fillList: function(list) {
            if(list.length == 0) {
                this.listDom.html('').addClass('nodata');
            } else {
                var html = '';
                for (var i = 0;i < list.length;i ++) {
                    html += '<li>';
                    html += '<span class="t1">'+list[i].nickname+'</span>';
                    html += '<span class="t2">'+lang('team_num')+'：'+list[i].now_number+'/'+list[i].number+'</span>';
                    html += '<span class="t3">【'+list[i].name+'】</span>';

                    if (parseInt(list[i].now_number) == parseInt(list[i].number)) {
                        html += '<button class="btn pop_btn_middle_disable">'+lang('join')+'</button>';
                    } else {
                        html += '<button class="btn pop_btn_middle" teamid="'+list[i].caption+'">'+lang('join')+'</button>';
                    }

                    html += '</li>';
                }
                this.listDom.html(html).removeClass('nodata');
            }
            this.myScroller.refresh();
        },

        //填充菜单
        fillMenu: function(list) {
            var html = '';
            for (var i = 0;i < list.length;i ++) {
                html += '<li>';
                html += '<span class="get">'+lang('countrybanquet_get')+'：</span>';
                html += '<span class="cost">'+lang('countrybanquet_sub')+'：' + (parseInt(list[i].sub_gold) > 0 ? ('<em class="gold">' + list[i].sub_gold + '</em>') : '<em class="free">'+lang('countrybanquet_free')+'</em>') + '</span>';
                html += '<span class="silver">' + list[i].silver + '</span>';
                html += '<span class="contribute">'+lang('countrybanquet_exp')+'<em class="gongxian">+' + list[i].contribute + '</em> ('+lang('countrybanquet_everyone')+')</span>';
                html += '<button class="btn pop_btn_middle" type="' + list[i].id + '">'+lang('countrybanquet_start')+'</button>';
                html += '</li>';
            }
            this.menuDom.find('ul').html(html);
        },

        //填充请个人信息
        fillMeal: function(team) {
            this.detailDom.find('#countrybanquet_type').html('【'+team.name+'】');
            this.detailDom.find('#countrybanquet_name').html(team.nickname);
            this.detailDom.find('#countrybanquet_num').html(team.now_number +'/'+ team.number);

            //判断是否是队长，队长多两个按钮
            if(player.info.uid == team.caption) {
                this.detailDom.find('#countrybanquet_gold').show();
                this.detailDom.find('#countrybanquet_start').show();
                this.detailDom.find('#countrybanquet_cancle').show();
                this.detailDom.find('#countrybanquet_getout').hide();
            } else {
                this.detailDom.find('#countrybanquet_gold').hide();
                this.detailDom.find('#countrybanquet_start').hide();
                this.detailDom.find('#countrybanquet_cancle').hide();
                this.detailDom.find('#countrybanquet_getout').show();
            }

            //队伍中信息
            var html = '';
            for (var i = 0; i < team.number; i++) {
                if(typeof team.list[i] != 'undefined') {
                    html += '<li>';
                    if(team.caption == player.info.uid && team.list[i].uid != player.info.uid) {
                        html += '<span uid="'+team.list[i].uid+'"></span>';
                    }
                    html += '<em class="name">' + team.list[i].nickname + '</em>';
                    html += '<em class="lv">Lv.' + team.list[i].level + '</em>';
                    html += '</li>';
                } else {
                    html += '<li>'+lang('waiting')+'</li>';
                }
            }

            //把列表中的加入按钮变灰
            this.listDom.find('.pop_btn_middle').attr('class', 'btn pop_btn_middle_disable');
            this.detailDom.find('ul').html(html);
            this.detailDom.show();
            this.memberScroller.refresh();
        },

        //封装的iscroll方法
        iscrollFunc: function() {
            if (this.myScroller == null) {
                this.myScroller = new iScroll('countrybanquetpop_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }

            if (this.memberScroller == null) {
                this.memberScroller = new iScroll('countrybanquetmember_scroller', {
                    snap:'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: true
                });
            }

        },

        /*定时刷新*/
        refreshStart: function() {
            var that = this;

            var func = function() {
                if(that.isrefresh == false) {
                    that.stopRefresh();
                    return false;
                }

                that.refresh(function() {
                    that.refreshtimer = setTimeout(func, 5000);
                });
            };
            that.refreshtimer = setTimeout(func, 5000);
        },

        /*停止定时刷新*/
        stopRefresh: function() {
            var that = this;
            that.isrefresh = false;
            clearTimeout(that.refreshtimer);
            that.refreshtimer = null;
        },

        /*刷新方法*/
        refresh: function(callback) {
            var that = this;
            var Func = function(res){
                if (res.status == 1) {
                    //更新界面次数
                    that.showTimes(res.info.times);
                    //队伍列表
                    that.fillList(res.list);
                    //判断用户状态
                    if(res.info.status == 1) {
                        //显示队伍信息
                        that.fillMeal(res.team);
                        that.menuDom.hide();//宴会信息
                        //that.detailDom.show();//队伍信息
                    } else if(res.info.status == 2) {
                        //提示队伍已解散
                        //显示宴会列表
                        xGame.toast.show(lang('countrybanquet_end'));
                        that.menuDom.show();//宴会信息
                        that.detailDom.hide();//队伍信息
                    } else if(res.info.status == 3) {
                        //提示上个队伍已完成
                        xGame.confirm.show(lang('countrybanquet'), lang('countrybanquet_complete', [res.add_silver, res.add_contribute]), 1, function() {
                            xGame.confirm.hide();
                        });
                        player.updateinfo({'silver':parseInt(res.all_silver)});
                        //显示宴会列表
                        that.menuDom.show();//宴会信息
                        that.detailDom.hide();//队伍信息
                    } else {
                        //显示宴会列表
                        that.menuDom.show();//宴会信息
                        that.detailDom.hide();//队伍信息
                    }
                    //执行回调函数
                    if(callback) {
                        callback();
                    }
                } else {
                    xGame.toast.show(lang('get_user_info_fail'));
                    return false;
                }
            };
            loadContents({'c':'banquet','m':'index','type':1}, Func, true);
            Func = null;
        },
        //所有的绑定事件
        bindAll: function() {
            var that = this;

            //菜单中的开宴按钮
            that.menuDom.find('ul').undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                
                var type = parseInt($(this).attr('type'));

                that.createTeam(type);
            });

            //详情中的取消按钮
            that.detailDom.undelegate('#countrybanquet_cancle', 'touchend').delegate('#countrybanquet_cancle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                //console.log();
                that.dismissTeam(1);
            });

            //详情中的离开按钮
            that.detailDom.undelegate('#countrybanquet_getout', 'touchend').delegate('#countrybanquet_getout', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.getoutTeam();
            });

            //详情中的开宴按钮
            that.detailDom.undelegate('#countrybanquet_start', 'touchend').delegate('#countrybanquet_start', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.actionTeam(0);
            });

            //详情中的强行开宴按钮
            that.detailDom.undelegate('#countrybanquet_gold', 'touchend').delegate('#countrybanquet_gold', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.actionTeam(1);
            });

            //详情中的强行提出队伍按钮
            that.detailDom.find('ul').undelegate('span', 'touchend').delegate('span', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.kickoutTeam($(this).attr('uid'));
            });

            //左侧队伍列表的键加入按钮
            that.listDom.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                //console.log();
                that.joinTeam($(this).attr('teamid'));
            });

        },

        //创建队伍
        createTeam : function(type){
            if(xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res){
                switch(parseInt(res.status)){
                    case 1:
                        /*TODO 建立队伍后的操作，比如更新左边列表*/
                        //显示队伍信息，隐藏宴会列表
                        //显示队伍信息
                        that.fillMeal(res.team);
                        //宴会信息
                        that.menuDom.hide();
                        //队伍信息
                        //that.detailDom.show();
                        //队伍列表
                        that.fillList(res.list);
                        //把列表中的加入按钮变灰
                        that.listDom.find('.pop_btn_middle').attr('class', 'btn pop_btn_middle_disable');
                        break;
                    case -1:
                        xGame.toast.show(lang('parameter_error'));
                        break;
                    case -2:
                        xGame.toast.show(lang('countrybanquet_not_end'));
                        break;
                    case -3:
                        xGame.toast.show(lang('countrybanquet_is_team'));
                        break;
                    case -4:
                        xGame.toast.show(lang('countrybanquet_get_info_error'));
                        break;
                    case -5:
                        xGame.toast.show(lang('countrybanquet_lack_gold'));
                        break;
                    case -6:
                        xGame.toast.show(lang('countrybanquet_lack_times'));
                        break;
                    case -8:
                        xGame.toast.show(lang('countrypop_contribution_limit'));
                        break;
                    case 2:
                        xGame.toast.show(lang('countrybanquet_not_open'));
                        break;
                    default:
                        xGame.toast.show(lang('countrybanquet_open_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({'c':'banquet','m':'create_team','id':type}, Func);
            Func = null;
        },

        //解散队伍
        dismissTeam : function(id){
            if(xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res){
                switch(parseInt(res.status)){
                    case 1:
                        //显示宴会列表
                        that.menuDom.show();//宴会信息
                        that.detailDom.hide();//队伍信息
                        //队伍列表
                        that.fillList(res.list);
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('countrybanquet_not_team'));
                        break;
                    case -3:
                        xGame.toast.show(lang('countrybanquet_cancel'));
                        break;
                    case -4:
                        xGame.toast.show(lang('countrybanquet_not_caption_dismiss'));
                        break;
                    case 2:
                        xGame.toast.show(lang('countrybanquet_not_open'));
                        break;
                    default:
                        xGame.toast.show(lang('countrybanquet_dismiss_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({'c':'banquet','m':'dismiss_team','id':id}, Func);
            Func = null;
        },

        //开宴方法
        actionTeam : function(type){
            if(xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res){
                switch(parseInt(res.status)){
                    case 1:
                        //更新次数
                        that.showTimes(res.times);
                        //显示宴会列表
                        that.menuDom.show();//宴会信息
                        that.detailDom.hide();//队伍信息
                        var str = '';
                        if(res.addition > 0) {
                            str = '<em style="color:yellow;">('+res.addition+'%)</em>';
                        }
                        xGame.toast.show(lang('countrybanquet_complete', [res.add_silver, res.add_contribute]) + str);
                        player.updateinfo({
                            'silver': res.all_silver,
                            'gold': res.all_gold
                        });
                        //队伍列表
                        that.fillList(res.list);
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('countrybanquet_not_team'));
                        break;
                    case -3:
                        xGame.toast.show(lang('countrybanquet_get_member_error'));
                        break;
                    case -4:
                        xGame.toast.show(lang('countrybanquet_not_caption_start'));
                        break;
                    case -5:
                        xGame.toast.show(lang('countrybanquet_get_info_error'));
                        break;
                    case -6:
                        //0是普通开宴，1是强行开宴
                        if (type == 0) {
                            xGame.toast.show(lang('countrybanquet_lack_gold'));
                        } else {
                            xGame.toast.show(lang('countrybanquet_lack_gold_open'));
                        }
                        break;
                    case -7:
                        xGame.toast.show(lang('countrybanquet_team_member_not'));
                        break;
                    case -8:
                        xGame.toast.show(lang('get_country_info_fail'));
                        break;
                    case 2:
                        xGame.toast.show(lang('countrybanquet_not_open'));
                        break;
                    default:
                        xGame.toast.show(lang('countrybanquet_action_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({'c':'banquet','m':'action','type':type}, Func);
            Func = null;
        },

        //加入队伍方法
        joinTeam : function(id){
            if(xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res){
                switch(parseInt(res.status)){
                    case 1:
                        //显示队伍信息
                        that.fillMeal(res.team);
                        //宴会信息
                        that.menuDom.hide();
                        //队伍列表
                        that.fillList(res.list);
                        //把列表中的加入按钮变灰
                        that.listDom.find('.pop_btn_middle').attr('class', 'btn pop_btn_middle_disable');
                        break;
                    case 4:
                        //自动开宴
                        xGame.confirm.show(lang('countrybanquet'), lang('countrybanquet_complete',[res.silver,res.contribute]), 1, function() {
                            xGame.confirm.hide();
                        });
                        //更新次数
                        that.showTimes(res.times);
                        //更新用户数据
                        player.updateinfo({'silver' : res.all_silver});
                        //宴会信息
                        that.menuDom.show();
                        that.detailDom.hide();
                        //队伍列表
                        that.fillList(res.list);
                        //把列表中的加入按钮变灰
                        that.listDom.find('.pop_btn_middle').attr('class', 'btn pop_btn_middle_disable');
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('countrybanquet_not_end'));
                        break;
                    case -3:
                        xGame.toast.show(lang('countrybanquet_get_member_error'));
                        break;
                    case -4:
                        xGame.toast.show(lang('countrybanquet_get_info_error'));
                        break;
                    case -5:
                        xGame.toast.show(lang('countrybanquet_full_member'));
                        break;
                    case -6:
                        xGame.toast.show(lang('countrybanquet_lack_times_join'));
                        break;
                    case -8:
                        xGame.toast.show(lang('countrypop_contribution_limit'));
                        break;
                    case 2:
                        xGame.toast.show(lang('countrybanquet_not_open'));
                        break;
                    default:
                        xGame.toast.show(lang('countrybanquet_join_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({'c':'banquet','m':'join_team','id':id}, Func);
            Func = null;
        },

        /*踢出队伍*/
        kickoutTeam: function(uid) {
            if(xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res){
                switch(parseInt(res.status)){
                    case 1:
                        //显示队伍信息
                        that.fillMeal(res.team);
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('countrybanquet_not_team_kickout'));
                        break;
                    case -3:
                        xGame.toast.show(lang('countrybanquet_get_info_error'));
                        break;
                    case -4:
                        xGame.toast.show(lang('countrybanquet_not_caption_kickout'));
                        break;
                    case -5:
                        xGame.toast.show(lang('countrybanquet_user_not_team'));
                        break;
                    case 2:
                        xGame.toast.show(lang('countrybanquet_not_open'));
                        break;
                    default:
                        xGame.toast.show(lang('countrybanquet_kickout_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({'c':'banquet','m':'kickout_team', 'uid':uid}, Func);
            Func = null;
        },

        //离开队伍方法
        getoutTeam : function(){
            if(xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res){
                switch(parseInt(res.status)){
                    case 1:
                        //显示宴会列表
                        that.menuDom.show();
                        that.detailDom.hide();
                        //队伍列表
                        that.fillList(res.list);
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('countrybanquet_not_team'));
                        break;
                    case 2:
                        xGame.toast.show(lang('countrybanquet_not_open'));
                        break;
                    default:
                        xGame.toast.show(lang('countrybanquet_dismiss_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({'c':'banquet','m':'getout_team'}, Func);
            Func = null;
        },

        unbindAll: function () {
            if (this.e) {
               this.e.find('#hidebtn').unbind('touchend');
               this.e.remove();
               this.e = null;
            }
            if (this.menuDom) {
                this.menuDom.find('ul').undelegate('.pop_btn_middle', 'touchend');
                this.menuDom.remove();
                this.menuDom = null;
            }
            if (this.detailDom) {
                this.detailDom.undelegate('#countrybanquet_cancle', 'touchend');
                this.detailDom.undelegate('#countrybanquet_getout', 'touchend');
                this.detailDom.undelegate('#countrybanquet_start', 'touchend');
                this.detailDom.undelegate('#countrybanquet_gold', 'touchend');
                this.detailDom.find('ul').undelegate('span', 'touchend');
                this.detailDom.remove();
                this.detailDom = null;
            }
            if (this.listDom) {
                this.listDom.undelegate('.pop_btn_middle', 'touchend');
                this.listDom.remove();
                this.listDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            if (this.myScroller) {
                this.myScroller.destroy();
                delete this.myScroller;
            }
            if (this.memberScroller) {
                this.memberScroller.destroy();
                delete this.memberScroller;
            }
            this.stopRefresh();
            this.unbindAll();
        },
    };
})();