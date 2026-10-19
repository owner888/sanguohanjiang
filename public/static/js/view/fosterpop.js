/* 培养 */
(function() {
    fosterPopClass = function() {
        this.e;
        this.isshow = false;
        this.data;
        this.nowViewId;
        this.nowViewK;
        this.nowMode = 0; //当前的培养模式
        this.myScroll;
    };
    fosterPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {

                if (!res || res.status == 2) {
                    xGame.toast.show(lang('have_no_hero_ready'));
                    $('#worldloading').hide();
                    return;
                }
                /* 保存数据 */
                that.data = res;

                /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
                that.e = xGame.loadPopTmpl('foster');
                that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) {
                        return false;
                    }
                    fosterpop.hide();
                    e.preventDefault();
                    e.stopPropagation();
                });

                /* 第二步 构建数据列表等 */
                that.buildlist();

                /* 展示弹窗 */
                that.e.show();
                $('#worldloading').hide();
                xGame.showpop('system', {'pop': 'foster'});

                /* 新手引导 */
                xGame.showGuide([1505]);

            };
            loadContents({
                'c': 'cultivate',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        /* 构建列表 */
        buildlist: function() {
            var that = this;

            var html = '';
            var n = 0;
            for (var i in that.data.list) {
                //增加k,very importing
                that.data.list[i].k = i;
                var item = that.data.list[i];
                html += '<li id="hero' + item.id + '" class="q' + item.quality + ' heroitem ' + (n == 0 ? ' selected': '') + '" herokey="' + i + '"><span class="avatar" style="background:url(' + xGame.avatar(item.image) + ') center center no-repeat;' + '"></span><p class="name">' + item.name + '</p></li>';
                n++;
            }
            that.e.find('.scroller_content').html(html);

            /* 创建滑动门 domid, 单页宽度 */
            that.myScroll = xGame.scroller('fosterpop_scroller', n * 132);

            /* 绑定事件(必须在滑动门之后) */
            $('.heroitem').unbind('click').bind('click',
            function() {
                var k = $(this).attr('herokey');
                if (that.data.list[k]) {
                    var data = that.data.list[k];
                    //处理selected
                    $('#hero' + that.nowViewId).removeClass('selected');
                    $('#hero' + data.id).addClass('selected');
                    that.nowViewId = data.id;
                    that.nowViewK = data.k;
                    that.showHero(data);
                }
            });
            //绑定滑动门左右按钮
            that.e.find('.scroller_btn_left').unbind('touchend').bind('touchend',
            function() {
                that.myScroll.scrollToPage('prev', 0);
                return false;
            });
            that.e.find('.scroller_btn_right').unbind('touchend').bind('touchend',
            function() {
                that.myScroll.scrollToPage('next', 0);
                return false;
            });

            /* 显示第一个武将数据*/
            var firstdata = helper.array_first(that.data.list);
            that.nowViewId = firstdata.id;
            that.nowViewK = firstdata.k;
            that.showHero(firstdata);
            that.e.find('#ok_btn').unbind('touchend').bind('touchend',
            function() {
                that.doFoster();
            });
            that.e.find('#replace_btn').unbind('touchend').bind('touchend',
            function() {
                that.saveFoster();
            });
            that.e.find('#stand_btn').unbind('touchend').bind('touchend',
            function() {
                that.standFoster();
            });
            /* 左侧培养类型 */
            var n = 0;
            if (that.data.rule) {
                var html = '';
                for (var i in that.data.rule) {
                    var rule = that.data.rule[i];
                    html += '<li ruleid="' + rule.id + '"' + (n == 0 ? ' class="selected"': '') + '><em></em>' + rule.name + ':' + (rule.gold ? ('<span class="icon_gold">' + rule.gold + lang('gold') + '</span>') : ('<span class="icon_coin">' + rule.silver + lang('silver') + '</span>')) + '</li>';
                    if (n == 0) { //默认第一种培养模式
                        that.nowMode = rule.id;
                    }
                    n++;
                }
                that.e.find('#fostertype_list').html(html);
                that.e.find('#fostertype_list').children('li').unbind('touchend').bind('touchend',
                function() {
                    that.e.find('#fostertype_list').children('li').each(function() {
                        $(this).removeClass('selected');
                    });
                    that.nowMode = $(this).attr('ruleid');
                    $(this).addClass('selected');
                });
            }

            //玩法说明按钮
            this.e.find('#info_btn').unbind('touchend').bind('touchend', function(e) {
                that.e.find('#fosterpop_info').css('display', 'block');
                e.preventDefault();
                e.stopPropagation();
            });

            this.e.find('#fosterpop_info').find('.mask').unbind('touchend').bind('touchend', function(e) {
                that.e.find('#fosterpop_info').css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });

            //玩法说明关闭按钮
            this.e.find('#info_hidebtn').unbind('touchend').bind('touchend', function(e) {
                that.e.find('#fosterpop_info').css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });

        },

        /* 单个武将信息 */
        showHero: function(data) {
            var that = this;

            that.e.find('.foster_name_wrap').children('.name').text(data.name);
            that.e.find('.foster_name_wrap').children('.lv').text('Lv.' + data.level);
            that.e.find('#old_data_wrap').children('.wuli').children('span').text(data.wuli + ' ' + (data.wuliup >= 0 ? ('+' + data.wuliup) : data.wuliup) + (parseInt(data.level) + 20 <= data.wuliup ? lang('full') : ''));
            that.e.find('#old_data_wrap').children('.zhili').children('span').text(data.zhili + ' ' + (data.zhiliup >= 0 ? ('+' + data.zhiliup) : data.zhiliup) + (parseInt(data.level) + 20 <= data.zhiliup ? lang('full') : ''));
            that.e.find('#old_data_wrap').children('.tili').children('span').text(data.tili + ' ' + (data.tiliup >= 0 ? ('+' + data.tiliup) : data.tiliup) + (parseInt(data.level) + 20 <= data.tiliup ? lang('full') : ''));
            /*根据类型加class*/
            that.e.find('#old_data_wrap').attr('class', 'data_wrap ' + (data.type == 1 ? 'wj': 'ms'));
            that.e.find('#new_data_wrap').attr('class', 'data_wrap ' + (data.type == 1 ? 'wj': 'ms'));

            if(data.cultivate && data.cultivate.gid > 0) {//需要处理未保存的数据
                that.fillNewData();
            } else {
                that.e.find('#new_data_wrap').hide();
                that.e.find('#stand_btn').hide();
                that.e.find('#replace_btn').hide();
                that.e.find('#ok_btn').show();
            }
        },

        /* 培养 */
        doFoster: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var heroid = that.nowViewId;
            var modeid = that.nowMode;

            var render = function(res) {
                if (res.status == 1) {

                    that.data.list[that.nowViewK].cultivate = res.info;//保存数据

                    that.fillNewData();

                    //本地金钱更新
                    player.updateinfo({
                        'silver': res.info.silver,
                        'gold': res.info.gold
                    });
                    //更新培养规则
                    if (parseInt(that.nowMode) > 1) { //元宝模式
                        that.data.rule[that.nowMode].gold = res.next;
                        $('#fostertype_list').find('.selected').children('span').text(res.next + lang('gold'));
                    } else { //银币模式
                        that.data.rule[that.nowMode].silver = res.next;
                        $('#fostertype_list').find('.selected').children('span').text(res.next + lang('silver'));
                    }

                    player.taskaction(7);
                    
                    //在平乱副本里
                    if (pacifypop && pacifypop.e) {
                        pacifypop.militaryDom.find('.pacify_gold span').text(res.info.gold);
                    }

                    /* 新手引导 */
                    //xGame.showGuide([1505, 1507, 1509, 1511, 1513]);
                } else if (res.status == 4) {
                    xGame.goPay(lang('no_gold_for_foster'));
                } else if (res.status == 3) {
                    xGame.toast.show(lang('no_silver_for_foster'));
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'cultivate',
                'm': 'roll',
                'gid': heroid,
                'mode': modeid
            },
            render);
            render = null;

        },

        /* 保存培养数据 */
        saveFoster: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;

            var successFunc = function(res) {
                if (res.status == 1) {
                    //step 1 更新本地数据和界面数值
                    var thehero = that.data.list[that.nowViewK];
                    var temparray = ['wuli', 'zhili', 'tili'];
                    var str = '';
                    for (var i = 0; i < temparray.length; i++) {
                        str = temparray[i];
                        that.data.list[that.nowViewK][str + 'up'] = parseInt(that.data.list[that.nowViewK].cultivate[str + 'up']);
                        that.e.find('#old_data_wrap').children('.' + str).children('span').text(that.data.list[that.nowViewK][str] + ' ' + (that.data.list[that.nowViewK][str + 'up'] >= 0 ? ('+' + that.data.list[that.nowViewK][str + 'up']) : that.data.list[that.nowViewK][str + 'up']) + (parseInt(that.data.list[that.nowViewK].level) + 20 <= that.data.list[that.nowViewK][str + 'up'] ? lang('full'): ''));
                    }

                    //step 2 界面恢复正常
                    that.e.find('#new_data_wrap').hide();
                    that.e.find('#stand_btn').hide();
                    that.e.find('#replace_btn').hide();
                    that.e.find('#ok_btn').show();

                    that.data.list[that.nowViewK].cultivate = null;

                    /* 新手引导 */
                    //xGame.showGuide([1506, 1508, 1510, 1512, 1514]);
                } else {
                    xGame.toast.show(lang('save_fail'));
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'cultivate',
                'm': 'save',
                'gid': that.nowViewId
            },
            successFunc);
            successFunc = null;
        },

        /* 放弃培养 */
        standFoster: function() {
            var that = this;

            var successFn = function(res) {
                if(res.status == 1) {
                    that.e.find('#new_data_wrap').hide();
                    that.e.find('#stand_btn').hide();
                    that.e.find('#replace_btn').hide();
                    that.e.find('#ok_btn').show();
                    that.data.list[that.nowViewK].cultivate = null;
                } else {
                    xGame.toast.show('undefined_error');
                }
            };
            loadContents({
                'c': 'cultivate',
                'm': 'giveup',
                'gid': that.nowViewId
            },
            successFn); //加载数据
            successFn = null;

        },

        fillNewData: function() {
            var that = this;

            var k = that.nowViewK;

            var equalnum = 0;
            var str = '';
            var temparray = ['wuli', 'zhili', 'tili'];
            for (var i = 0; i < temparray.length; i++) {
                var temp = temparray[i];
                var data_temp = that.data['list'][k][temp];//旧数据
                var tempup = temparray[i] + 'up';
                var data_tempup = that.data['list'][k]['cultivate'][tempup];//新数据

                var str = (i == 0 ? lang('wuli') : (i == 1 ? lang('zhili') : lang('tili'))) + ':<span>' + data_temp;
                if (data_tempup > 0) {
                    str += ' +' + data_tempup + '</span>';
                } else if (data_tempup == 0) {
                    str += ' +0</span>';
                } else {
                    str += ' ' + data_tempup;
                }
                //比较是上升还是下降
                var nowhero = that.data.list[k];
                if (data_tempup > nowhero[tempup]) {
                    str += '<em class="up">↑</em>';
                } else if (data_tempup < nowhero[tempup]) {
                    str += '<em class="down">↓</em>';
                } else {
                    equalnum++;
                    str += '<em class="up"></em>';
                }
                that.e.find('#new_data_wrap').find('.' + temp).html(str);
            }
            temp = tempup = temparray = null;
            that.e.find('#new_data_wrap').show();
            that.e.find('#stand_btn').show();
            if (equalnum < 3) {
                that.e.find('#replace_btn').show();
            } //培养后的数值与上一次全部相等时，不显示保存按钮
            that.e.find('#ok_btn').hide();
        },

        unbindAll: function() {
            this.e.find('.content').find('.hidebtn').unbind('touchend');
            $('.heroitem').unbind('click');
            this.e.find('.scroller_btn_left').unbind('touchend');
            this.e.find('.scroller_btn_right').unbind('touchend');
            this.e.find('#ok_btn').unbind('touchend');
            this.e.find('#replace_btn').unbind('touchend');
            this.e.find('#stand_btn').unbind('touchend');
            this.e.find('#fostertype_list').children('li').unbind('touchend');
            this.e.remove();
            this.e = null;
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.data = null;
            if (this.myScroll) {
                this.myScroll.destroy();
                delete this.myScroll;    
            }
            
            this.nowViewId = null;
            this.nowViewK = null;
            this.nowMode = null;
            
            if (xGame.nowWorld == 'country' && countryWorld != undefined) {
                countryWorld.showLeftBar();
            }
            if (this.e) {
                this.unbindAll();
            }
            player.reloadInfo();
        },
    };
})();