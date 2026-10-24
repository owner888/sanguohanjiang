/* 国家骰子 */
(function() {
    countrydicePopClass = function() {
        this.e;
        this.isshow = false;
        this.myScroller;
        this.animCallback;
        this.animNum = 0;
        this.animCfgY = {//动画的Y坐标
            1: [0,40,199,175,161,35,0,17,45,17,30,0,30,17,0],
            2: [0,11,143,175,151,70,60,0,73,65,30,0,35,27,0],
            3: [0,52,173,205,175,117,48,10,60,22,17,0,13,9,0]
        };
        this.cfg_rect = null;
    };
    countrydicePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                if (res.status == 1) {
                    that.buildPage(res);
                } else {
                    var error_msg = '';

                    res.status = parseInt(res.status);
                    switch(res.status) {
                        case -1 :
                            error_msg = lang('you_have_no_country');
                            break;
                        case -3 :
                            error_msg = lang('countrydicepop_open_countrylv');
                            break;
                        default:
                            error_msg = lang('countrydicepop_enter_error');
                            break;
                    }
                    xGame.toast.show(error_msg);
                    $('#worldloading').hide();
                }
                that = null;
            };
            loadContents({
                'c': 'dice',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;
        },

        buildPage: function(data) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('countrydice');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                countrydicepop.hide();
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
            that.e.find('#intro_btn').unbind('touchend').bind('touchend',
            function(e) {
                that.showIntroPage();
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
            that.e.find('#countrydicepop_info_hide').unbind('touchend').bind('touchend',
            function(e) {
                that.hideIntroPage();
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
            that.e.find('#special_wrap').undelegate('.pop_btn_small.special_btn', 'touchend').delegate('.pop_btn_small.special_btn', 'touchend',
            function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                var rewardid = $(this).attr('rewardid');
                if(rewardid > 0) {
                    that.fetchBigReward(rewardid);
                }
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
            that.e.find('#countrydicepop_wrap').undelegate('.pop_btn_small.fetch_btn', 'touchend').delegate('.pop_btn_small.fetch_btn', 'touchend',
            function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                that.fetchLastWeek();
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
            that.e.find('#playdice_wrap').undelegate('.pop_btn_middle.do_btn', 'touchend').delegate('.pop_btn_middle.do_btn', 'touchend',
            function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                that.shakeDice();
                e.preventDefault();
                e.stopPropagation();
                return false;
            });

            data.last_week = data.last_week || { status: 0 };
            data.member = data.member || { points: 0, times: 10, cost: 10, dice1: 6, dice2: 6, dice3: 6 };
            data.list = data.list || [];

            that.buildLastWeek(data.last_week);
            //复用
            that.buildMyData(data);
            //初始化骰子
            var i = 1;
            that.e.find('#playdice_wrap').find('.dice_wrap').each(function() {
                var diceVal = (data.member && data.member['dice'+i]) ? data.member['dice'+i] : 6;
                $(this).append(that.createDice(diceVal));
                i++;
            });

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();


        },

        buildLastWeek: function(last_week) {
            if(last_week && last_week.status == 1) {
                this.e.find('#lastweek_data').find('.score').children('span').html(lang('countrydicepop_x_score', [last_week.points]));
                this.e.find('#lastweek_data').find('.rank').children('span').html(last_week.now_rank);
                this.e.find('#lastweek_data').find('.reward').children('span').html(last_week.reward);
                if(last_week.get_rank > 0) {
                    this.e.find('#fetch_btn').attr('class', 'pop_btn_small_disable btn fetch_btn').html(lang('warena_have_fetch'));
                } else {
                    this.e.find('#fetch_btn').attr('class', 'pop_btn_small btn fetch_btn').html(lang('warena_fetch'));
                }
            }
        },

        buildMyData: function(data) {

            var html = '';
            for(var i = 0; i < data.list.length; i++) {
                html += '<li '+(data.list[i].uid == player.info.uid ? 'class="self"' : '')+'>';
                html +=     '<p class="rank">'+data.list[i].rank+'</p>';
                html +=     '<p class="name">'+data.list[i].nickname+'</p>';
                html +=     '<p class="score">'+lang('countrydicepop_x_score', [data.list[i].points])+'</p>';
                html +=     '<p class="reward t0">'+data.list[i].reward+'</p>';
                html += '</li>';
            }
            this.e.find('#countrydicepop_list').html(html);

            if(!this.myScroller) {
                this.myScroller = new iScroll('countrydicepop_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.myScroller.refresh();

            var html = '';
            for(var i = 0; i < data.reward.length; i ++) {
                html += '<div class="item">';
                html += '    <p class="word">'+lang('countrydicepop_weekreward_xx', [data.reward[i].points])+'</p>';
                html += '    <p class="reward t0">'+data.reward[i].reward+'</p>';
                var btn_class = data.reward[i].status == 1 ? 'pop_btn_small' : 'pop_btn_small_disable';
                var btn_word = data.reward[i].status == 2 ? lang('warena_have_fetch') : lang('warena_fetch');
                html += '    <button class="'+btn_class+' btn special_btn" rewardid="'+data.reward[i].id+'" id="special_btn'+data.reward[i].id+'">'+btn_word+'</button>';
                html += '</div>';
            }
            this.e.find('#special_wrap').html(html);

            this.e.find('#playdice_wrap').find('#week_allscore').children('span').html(data.member.points);
            this.e.find('#playdice_wrap').find('#day_lefttime').children('span').html(data.member.times);
            if(data.member.times <= 0) {
                this.e.find('#do_btn').attr('class', 'pop_btn_middle_disable btn do_btn');
            }
        },

        shakeDice: function() {
            var that = this;

            var Func = function(res) {
                res.status = parseInt(res.status);
                if(res.status == 1) {
                    //自己组装骰子信息
                    var dicedata = {};
                    var scores = 0;
                    for(var i = 1; i <= 3; i ++) {
                        dicedata[i] = res.member['dice'+i];
                        scores += dicedata[i];
                    }
                    //执行动画并处理结果
                    that.playAnim(dicedata, function() {
                        that.buildMyData(res);
                        var numberOjb = that.createPicNum('-' + scores + '');
                        that.e.find('#countrydicepop_wrap').append($('<div id="getscore_wrap"></div>').append(numberOjb.e).css('width', numberOjb.e.css('width')));
                        xGame.doAnimate($('#getscore_wrap'), 'fadeInUp', '1s',
                        function() {
                            numberOjb = null;
                            xGame.unlock();
                            $('#getscore_wrap').remove();
                        });
                    });
                } else {
                    var error_msg = '';
                    switch(res.status) {
                        case -1 :
                            error_msg = lang('you_have_no_country');
                            break;
                        case -3 :
                            error_msg = lang('countrydicepop_countrylv_low');
                            break;
                        case -5 :
                            error_msg = lang('countrydicepop_times_low');
                            break;
                        default:
                            error_msg = lang('countrydicepop_enter_error');
                            break;
                    }
                    xGame.toast.show(error_msg);
                    xGame.unlock();
                }

            };
            loadContents({
                'c': 'dice',
                'm': 'shake_dice'
            },
            Func); //加载数据
            Func = null;
        },

        createDice: function(num) {
            var mx = (num - 1) * 82;
            var html = '<span class="dice" style="background-position: -' + mx + 'px 50%;"></span>';
            return html;
        },

        playAnim: function(dicedata, callback) {
            var that = this;

            this.initDiceAnimCallbackFunction(callback);
            for(var i in dicedata) {
                var anim = this.createDiceAnim(i, dicedata[i]);
                this.e.find('#dice_wrap'+i).html(anim.obj);
                anim.play();
            }
        },

        createDiceAnim: function(id, dice) {
            var that = this;

            var timeval = 56;

            if(this.cfg_rect == null) {
                this.cfg_rect = {};//整个的动画配置
                for(var i = 1; i <= 3; i ++) {
                    this.cfg_rect[i] = [];
                    for(var x = 0; x < 13; x ++) {
                        var rect_mx = x < 7 ? (82 * x) : (82 * (x - 7));//资源图X轴
                        var rect_my = x < 7 ? 0 : 82;//资源图Y轴
                        var rect_x = 0;//元素x坐标(left)
                        var rect_y = this.animCfgY[i][x] * -1;//元素y坐标(top)

                        this.cfg_rect[i].push({
                            rect: [rect_mx, rect_my, rect_x, rect_y],
                            interval: timeval
                        });
                    }
                }
            }

            //console.log('创建骰子动画' + id + ': ' + dice);

            var obj = new movieClipClass({
                "img": ximg(staticUrl + 'img/pop/countrydice_anim.png'),
                "width": 82,
                "height": 82,
                "x": 0,
                "y": 0,
                "loop": false,
                "endDestory": true,
                "callback": function() {
                    that.e.find('#dice_wrap'+id).html(that.createDice(dice));
                    that.checkDiceAnimDone();
                },
                "frames": this.cfg_rect[id]
            });
            return obj;
        },

        /* 初始化 */
        initDiceAnimCallbackFunction: function(callback) {
            //console.log('初始化动画回调');
            this.animCallback = callback;
            this.animNum = 3;
        },

        /* 检查动画是否完成 */
        checkDiceAnimDone: function() {
            //console.log('检查动画是否完成');
            this.animNum--;
            if(this.animNum <= 0 && this.animCallback) {//done
                //console.log('动画完成，执行回调');
                this.animCallback();
                this.animCallback = null;
            }
        },

        showIntroPage: function() {
            this.e.find('#countrydicepop_info_dom').show();
        },

        hideIntroPage: function() {
            this.e.find('#countrydicepop_info_dom').hide();
        },

        createPicNum: function(number) {
            var numAnim = new Num({
                id: helper.createUUID("bloodnum"),
                src: staticUrl + 'img/battle/numtext_add.png',
                max: number.length,
                gap: 24,
                height: 30,
                x: 0,
                y: 0,
                value: number
            });

            return numAnim;
        },

        fetchBigReward: function(rewardid) {
            var that = this;

            var Func = function(res) {
                res.status = parseInt(res.status);
                if(res.status == 1) {
                    player.updateinfo({
                        gold: res.all_gold
                    });
                    xGame.toast.show(lang('receive_success') + '<span class="add_gold">'+res.add+'</span>');
                    xGame.unlock();
                    that.e.find('#special_btn'+rewardid).attr('class', 'pop_btn_small_disable btn special_btn').html(lang('warena_have_fetch'));

                } else {
                    var error_msg = '';
                    switch(res.status) {
                        case -1:
                            error_msg = lang('you_have_no_country');
                            break;
                        case -3:
                            error_msg = lang('countrydicepop_countrylv_low');
                            break;
                        case -4:
                            error_msg = lang('countrydicepop_lastweek_not_join');
                            break;
                        case -5:
                            error_msg = lang('countrydicepop_fetched');
                            break;
                        case -7:
                            error_msg = lang('countrydicepop_score_low_for_fetch');
                            break;
                        default:
                            error_msg = lang('countrydicepop_fetch_error');
                            break;
                    }
                    xGame.toast.show(error_msg);
                    xGame.unlock();
                }
            };
            loadContents({
                'c': 'dice',
                'm': 'get_reward',
                'id': rewardid
            },
            Func);
            Func = null;
        },

        fetchLastWeek: function() {
            var that = this;
            var Func = function(res) {
                res.status = parseInt(res.status);
                if(res.status == 1) {
                    player.updateinfo({
                        gold: res.all_gold
                    });
                    xGame.toast.show(lang('receive_success') + '<span class="add_gold">'+res.get+'</span>');
                    xGame.unlock();
                    that.e.find('#fetch_btn').attr('class', 'pop_btn_small_disable btn fetch_btn').html(lang('warena_have_fetch'));
                } else {
                    var error_msg = '';
                    switch(res.status) {
                        case -1:
                            error_msg = lang('you_have_no_country');
                            break;
                        case -3:
                            error_msg = lang('countrydicepop_countrylv_low');
                            break;
                        case -4:
                            error_msg = lang('countrydicepop_lastweek_not_join');
                            break;
                        case -5:
                            error_msg = lang('countrydicepop_fetched');
                            break;
                        default:
                            error_msg = lang('countrydicepop_fetch_error');
                            break;
                    }
                    xGame.toast.show(error_msg);
                    xGame.unlock();
                }
            };
            loadContents({
                'c': 'dice',
                'm': 'get_last_week_reward'
            },
            Func);
            Func = null;
        },

        unbindAll: function () {
            this.e.find('.content').find('.hidebtn').unbind('touchend');
            this.e.find('#intro_btn').unbind('touchend');
            this.e.find('#countrydicepop_info_hide').unbind('touchend');
            this.e.find('#special_wrap').undelegate('.pop_btn_small.special_btn', 'touchend');
            this.e.find('#countrydicepop_wrap').undelegate('.pop_btn_small.fetch_btn', 'touchend');
            this.e.find('#playdice_wrap').undelegate('.pop_btn_middle.do_btn', 'touchend');
        },

        hide: function() {
            xGame.unlock();
            
            this.cfg_rect = null;
            this.isshow = false;
            
            if (this.myScroller) {
                this.myScroller.destroy();
                delete this.myScroller;
            }
            
            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }
            
        },
    };
})();