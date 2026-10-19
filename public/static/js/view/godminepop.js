/* 神矿 */
(function() {
    godminePopClass = function() {
        this.e;
        this.e2;
        this.isshow = false;
        this.isTimeout = [];
    };
    godminePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                if (res.status == 1) {
                    that.initFirst(res);
                } else if (res.status == -1) {
                    xGame.toast.show(lang('activity_closed'));
                    $('#worldloading').hide();
                } else {
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                }
            };
            loadContents({
                'c': 'jadegamble',
                'm': 'index'
            },
            initRender, true);
            initRender = null;

        },

        initFirst: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('godmine');
            that.e.find('#hidebtn').unbind('touchend').one('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                godminepop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            xGame.updateCd('godmine', res.cd);

            //更新我的宝石数量
            that.updateMygem(res.jade);
            //for(var i = 1; i < 4; i++) {
            //    that.e.find('#mygem'+i).html(res.jade[i]);
            //}

            for(var i = 0; i < res.list.length; i++) {
                var mineitem = that.e.find('#godmine'+res.list[i].id);
                mineitem.find('.do_btn').children('em').html('x' + res.list[i].price);
                mineitem = null;
            }
            that.bindAll();

            //处理info
            if(res.info && res.info.status == 1) {
                that.openGamble(res.info);
            }

            that.e.show();
            $('#worldloading').hide();
        },

        bindAll: function() {
            var that = this;
            that.e.undelegate('.do_btn', 'touchend').delegate('.do_btn', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var mineid = $(this).attr('mine');
                that.doMining(mineid);
            });
        },
        
        /* 锄头动画 */
        chutou: function(id, res) {
            var that = this;
            var movieclip;

            //创建动画底图
            var mc = new movieClipClass({
                "img": ximg(staticUrl + 'img/pop/godminepop_chutou.png'),
                "width": 210,
                "height": 160,
                "x": 50,
                "y": 180,
                "loop": false,
                "endDestory": true,
                "callback": function() {
                    that.openGamble(res.info);
                    xGame.islock = false;
                },
                "frames": [{
                    rect: [0, 0],
                    interval: 50
                },
                {
                    rect: [210, 0],
                    interval: 50
                },
                {
                    rect: [420, 0],
                    interval: 50
                },
                {
                    rect: [0, 160],
                    interval: 50
                },
                {
                    rect: [210, 160],
                    interval: 50
                },
                {
                    rect: [420, 160],
                    interval: 50
                },
                {
                    rect: [0, 0],
                    interval: 50
                },
                {
                    rect: [210, 0],
                    interval: 50
                },
                {
                    rect: [420, 0],
                    interval: 50
                },
                {
                    rect: [0, 160],
                    interval: 50
                },
                {
                    rect: [210, 160],
                    interval: 50
                },
                {
                    rect: [420, 160],
                    interval: 50
                }]
            });
            //开始播放
            mc.appendTo(this.e.find('#godmine' + id));
            mc.play();
        },
        
        /* 翻倍特效 */
        shineAnim: function(callback) {
            var that = this;
            var movieclip;

            //创建动画底图
            var mc = new movieClipClass({
                "img": ximg(staticUrl + 'img/pop/godminepop_shine.png'),
                "width": 380,
                "height": 190,
                "x": 240,
                "y": 0,
                "loop": false,
                "endDestory": true,
                "callback": function() {
                    callback();
                    xGame.islock = false;
                },
                "frames": [{
                    rect: [0, 0],
                    interval: 40
                },
                {
                    rect: [380, 0],
                    interval: 40
                },
                {
                    rect: [0, 190],
                    interval: 40
                },
                {
                    rect: [380, 190],
                    interval: 40
                },
                {
                    rect: [0, 380],
                    interval: 40
                },
                {
                    rect: [380, 380],
                    interval: 40
                }]
            });
            //开始播放
            mc.appendTo(this.e2.find('#gameminegameble_page'));
            mc.play();
        },
        
        /*  失败特效 */
        failAnim: function(callback) {
            var that = this;
            this.e2.find('#itemline1').find('span').remove();
            var obj = $('<div class="fail_down"></div>');
            obj.appendTo(this.e2.find('#godminegamblepop_wrap')).unbind('webkitAnimationEnd').one('webkitAnimationEnd', function() {
                that.isTimeout.push(setTimeout(function() {
                    obj.remove();
                    callback();
                },1000));
            });
        },
        
        /* 挖矿 */
        doMining: function(mineid) {
            var that = this;

            var render = function(res) {
                if(res.status == 1) {
                    player.updateinfo({'gold': res.gold});
                    xGame.islock = true;
                    that.chutou(mineid, res);
                } else if(res.status == 2) {//挖掘成功或还有未领取的，进入赌的界面
                    xGame.islock = true;
                    that.chutou(mineid, res);
                } else if(res.status == -1) {
                    xGame.toast.show(lang('activity_end'));
                    that.closeGamble();
                    that.hide();
                } else if(res.status == -3) {
                    xGame.goPay(lang('godminepop_gold_not_enough'));
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
            };
            loadContents({
                'c': 'jadegamble',
                'm': 'mining',
                'id': mineid
            },
            render); //加载数据
            render = null;
        },

        closeGamble: function() {
            var that = this;
            that.e.find('#godminepop_page').removeClass('nolight');
            that.e2.remove();
            that.e2 = null;
        },

        openGamble: function(info) {
            var that = this;
            that.e.find('#godminepop_page').addClass('nolight');

            that.e2 = xGame.loadPopTmpl('godminegamble');

            that.updateGamble(info);

            that.e2.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend', function() {
                that.doHarvest();
            });
            that.e2.undelegate('.pop_btn_big', 'touchend').delegate('.pop_btn_big', 'touchend', function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.doGamble();
            });
            that.e2.show();
        },

        /* 更新页面 */
        updateGamble: function(info) {

            this.e2.find('.mine').attr('class', 'mine mine'+info.type);

            //当前可获得
            var numberOjb = this.createPicNum('-' + info.num + '');
            this.e2.find('#nowjades').html(numberOjb.e).css('width', numberOjb.e.css('width'));
            numberOjb = null;

            //当前倍数
            if(info.multiple == 1) {//初始无倍数
                this.e2.find('#itemline1').children('span').attr('class', 'nodouble').html(lang('no_word'));
            } else {
                this.e2.find('#itemline1').children('span').attr('class', 'double'+info.multiple).html('');
            }
            if(info.price && info.price > 0) {
                //翻倍可获得
                this.e2.find('#itemline2').children('span').attr('class', 'number gem'+info.type).html(info.next+'');
                //下次花费
                this.e2.find('#nextcost').html(info.price+'');
            } else {
                this.e2.find('#itemline2').children('span').attr('class', 'number').html(lang('no_word'));
                this.e2.find('#nextcost').hide();
                this.e2.find('#double_btn').attr('class', 'btn pop_btn_big_disable');
                this.e2.find('#fetch_btn').attr('class', 'btn pop_btn_middle_disable');
            }
            
        },

        /* 赌 */
        doGamble: function() {
            var that = this;

            var render = function(res) {
                if(res.status == 1) {

                    if(res.gold) {
                       player.updateinfo({'gold': res.gold}); 
                    }
                    
                    if(res.success == 1 && res.info.price) {//判断赌博是否胜利或能继续翻倍
                        //xGame.toast.show(lang('godminepop_gamble_success'));
                        xGame.islock = true;
                        that.shineAnim(function() {
                            //更新页面信息
                            that.updateGamble(res.info);
                        });

                        that.showReward(true, res.fb_info);
                        
                    } else {//失败或无法继续翻倍

                        var msg_title = '';
                        if(res.success == 1) {
                            //这个方法没有延时所以传入settimeout
                            xGame.islock = true;
                            that.shineAnim(function() {
                                that.updateGamble(res.info);
                                that.isTimeout.push(setTimeout(function() {
                                    that.closeGamble();
                                    xGame.confirm.show(lang('godminepop_msg_title1'), lang('godminepop_msg_content', [res.info.multiple, res.add_jade.type, res.add_jade.num]), 1,
                                    function() {
                                        that.updateMygem(res.jade);
                                       xGame.confirm.hide();
                                    });
                                }, 1000));
                            });

                            that.showReward(true, res.fb_info);
                        } else {
                            //这个方法有延时了，所以不用settimeout
                            xGame.islock = true;
                            that.e2.find('#fetch_btn').attr('class', 'btn pop_btn_middle_disable');
                            that.e2.find('#double_btn').attr('class', 'btn pop_btn_big_disable');
                            that.failAnim(function() {
                                that.closeGamble();
                                xGame.islock = false;
                                xGame.confirm.show(lang('godminepop_msg_title2'), lang('godminepop_msg_content', [res.info.multiple, res.add_jade.type, res.add_jade.num]), 1,
                                function() {
                                    that.updateMygem(res.jade);
                                    xGame.confirm.hide();
                                });
                            });

                            that.showReward(false, res.fb_info);
                        }

                    }
                } else if(res.status == 2) {
                    xGame.toast.show(lang('godminepop_double_is_limit'));
                } else if(res.status == -4) {
                    xGame.goPay(lang('godminepop_gold_not_enough'));
                } else if(res.status == -1) {
                    xGame.toast.show(lang('activity_end'));
                    that.closeGamble();
                    that.hide();
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
            };
            loadContents({
                'c': 'jadegamble',
                'm': 'gambling'
            },
            render); //加载数据
            render = null;
        },

        updateMygem: function(data) {
            for(var i = 1; i <= 3; i++) {
                if(typeof data[i] != 'undefiend') {
                   this.e.find('#mygem'+i).html(data[i]);
               }
            }
        },

        /* 收获宝石 */
        doHarvest: function() {
            var that = this;

            var render = function(res) {
                if(res.status == 1) {
                    xGame.toast.show(lang('get') + '&nbsp;<span class="gem' + res.add_jade.type + '">&nbsp;×&nbsp;' + res.add_jade.num + '</span>');
                    that.closeGamble();
                    that.updateMygem(res.jade);
                } else if(res.status == -1) {
                    xGame.toast.show(lang('activity_end'));
                    that.closeGamble();
                    that.hide();
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
            };
            loadContents({
                'c': 'jadegamble',
                'm': 'harvest'
            },
            render); //加载数据
            render = null;
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

        showReward: function (success, info) {
            
            var html = '';
            var value = info.value;

            switch (parseInt(info.type)) {
                case 1: // 元宝
                    html = lang('acttreasurepop_msg1', [value]);
                    break;
                case 2: // 银币
                    html = lang('acttreasurepop_msg2', [value]);
                    player.updateinfo({'silver': info.update.silver});
                    break;
                case 3: // 黄宝石
                    html = lang('acttreasurepop_msg3', [2, value, lang('yellow_jade')]);
                    break;
                case 4: // 紫宝石
                    html = lang('acttreasurepop_msg3', [3, value, lang('purple_jade')]);
                    break;
                case 5: // 原魂
                    html = lang('acttreasurepop_msg4', [value]);
                    break;
                case 6: // 玉石
                    html = lang('acttreasurepop_msg5', [value]);
                    break;
                case 7: // 声望
                    html = lang('acttreasurepop_msg6', [value]);
                    break;
                case 9: // 宝石碎片
                    html = lang('acttreasurepop_msg8', [value]);
                    break;
                case 10: // 突飞卡
                    html = lang('acttreasurepop_msg9', [value]);
                    break;
                case 11: // 蓝宝石
                    html = lang('acttreasurepop_msg3', [1, value, lang('blue_jade')]);
                    break;
                case 13: // 军令
                    html = lang('acttreasurepop_msg12', [value]);
                    player.updateinfo({'act': info.update.act});
                    break;
                case 14: // 精炼石
                    html = lang('acttreasurepop_msg14', [value]);
                    break;
                case 15: // 五彩石
                    html = lang('acttreasurepop_msg15', [value]);
                    break;
                case 16: // 上古血玉
                    html = lang('acttreasurepop_msg16', [value]);
                    break;
                default :
                    break;
            }

            if (success) {
                xGame.toast.show('<span class="reward_crit rc_godmine_success">' + html + '</span>');
            } else {
                xGame.toast.show('<span class="reward_crit rc_godmine_fail">' + html + '</span>');
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            xGame.updateCd('godmine', 0);
            if (this.e) {
                this.e.undelegate('.do_btn', 'touchend');
                this.e.find('#hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.e2) {
                this.e2.undelegate('.pop_btn_middle', 'touchend');
                this.e2.undelegate('.pop_btn_big', 'touchend');
                this.e2.remove();
                this.e2 = null;
            }
            
        }
    };
})();