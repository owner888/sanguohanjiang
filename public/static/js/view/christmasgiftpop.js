/* 圣诞礼包*/
(function() {
    christmasgiftPopClass = function() {
        this.e;
        this.isshow = false;
        this.rewardDom;
        this.myScroller;
        this.anim = null;
        this.autoThread;
        this.isRefresh = false;//是否是刷新
        this.lastDay;
        this.rewardData;//奖励数据
        this.deleteThread;//删除线程
    };

    christmasgiftPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                
                if (res.status == '2') {
                    xGame.toast.show(lang('activity_end'));
                    $('#worldloading').hide();
                    return false;
                } else if (res.status == '-1') {
                    xGame.toast.show(lang('get_reward_info_fail'));
                    $('#worldloading').hide();
                    return false;
                } else if (res.status == '1') {
                    that.initPage(res);
                    that.autoThread = setTimeout(function() {
                        if (christmasgiftpop.e) {
                            christmasgiftpop.autoRefresh();
                        }
                    }, 3000);
                    
                } else {
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                    return false;
                }
            };
            
            //加载数据
            loadContents({
                'c': 'christmasgift',
                'm': 'index'
            },
            Func);
            Func = null;
        },
        
        autoRefresh: function() {
            
            var that = this;
            var Func = function(res) {
                
                if (res.status == '1') {
                    that.autoThread = setTimeout(function() {
                        if (christmasgiftpop.e) {
                            christmasgiftpop.lastDay = res.last_day;
                            christmasgiftpop.isRefresh = true;
                            christmasgiftpop.updateList(res.list);
                            christmasgiftpop.autoRefresh();
                        }
                    }, 3000);
                } 
            };

            var errorFunc = function() {
                that.autoThread = setTimeout(function() {
                    if (christmasgiftpop.e) {
                        christmasgiftpop.autoRefresh();
                    }
                }, 3000);
            };
            
            //加载数据
            loadContents({
                'c': 'christmasgift',
                'm': 'index'
            },
            Func, true, errorFunc, true);
            Func = null;
            errorFunc = null;
        },
        
        initPage: function(res) {
            
            this.e = xGame.loadPopTmpl('christmasgift');

            if (player.info.christmasgift_type == 1) {
                this.e.addClass('new');
            }
            
            this.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                christmasgiftpop.hide();
                e.preventDefault();
            });
            
            this.rewardDom = this.e.find('#christmasgift_list');
            this.e.find('#christmasgift_time').html(res.time);
            this.lastDay = res.last_day;
            this.rewardData = res.list;
            this.buildList(res.list);
            this.setCd(parseInt(res.cd));
            this.bindAll();
            
            this.myScroller = new iScroll('christmasgift_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            this.myScroller.refresh();
            
            
            this.e.show();
            $('#worldloading').hide();
        },
        
        buildList: function(list) {
            var html = '';
            var len = list.length;
            var str = '';
            for (var i = 0; i < len;i ++) {
                str = '<b>' + lang('buy') + '</b><em>x' + list[i].price + '</em>';
                if (parseInt(list[i].is_buy) == 1 && parseInt(list[i].is_get) == 0) {

                    html += '<li id="christmasgift_l' + list[i].id + '">';
                    if (parseInt(list[i].is_open) == 1) {
                        //可购买
                        html += '<button class="btn pop_btn_middle" gid="' + list[i].id + '" str="' + str + '"><b>' + lang('buy') + '</b><em>x' + list[i].price + '</em></button>';
                    } else {
                        html += '<button class="btn pop_btn_middle_disable vip" gid="' + list[i].id + '" str="' + str + '"><b>' + lang('buy') + '</b><em>x' + list[i].price + '</em></button>';
                        html += '<div class="title vip_limit">' + lang('christmasgiftpop_vip_limit', [list[i].vip]) + '</div>';
                    }
                } else {
                    //按钮变灰
                    html += '<li id="christmasgift_l' + list[i].id + '" class="show_cd">';
                    html += '<button class="btn pop_btn_middle_disable" gid="' + list[i].id + '" str="' + str + '">' + lang('christmasgiftpop_had_buy') + '</button>';
                }
                if (parseInt(list[i].num) > 0) {
                    html += '<em class="limited" str="' + lang('christmasgiftpop_today_left', [list[i].num]) + '">' + lang('christmasgiftpop_today_left', [list[i].buytimes]) + '</em>';
                }                
                html +='<div class="title">' + list[i].name + '</div>';
                html += this.buildRewardHtml(list[i]);
                if (this.lastDay == 1) {
                    html += '<div class="cd last">' + lang('anniversarypop_last_day') + '</div>';
                } else {
                    html += '<div class="cd"></div>';
                }
                html += '</li>';
            }
            this.rewardDom.html(html);
            html = null;
        },
        
        //更新列表
        updateList: function(list) {
            var len = list.length;
            var obj;
            for (var i = 0; i < len;i ++) {
                obj = $('#christmasgift_list').find('li').eq(i);
                if (parseInt(list[i].is_buy) == 1 && parseInt(list[i].is_get) == 0) {

                    obj.removeClass('show_cd');
                    if (parseInt(list[i].is_open) == 1) {
                        //可购买
                        obj.find('button').attr('class', 'btn pop_btn_middle').html('<b>' + lang('buy') + '</b><em>x' + list[i].price + '</em>');
                    } else {
                        obj.find('button').attr('class', 'btn pop_btn_middle_disable vip').html('<b>' + lang('buy') + '</b><em>x' + list[i].price + '</em>');
                    }
                } else {
                    //按钮变灰
                    obj.attr('class', 'show_cd');
                    obj.find('button').attr('class', 'btn pop_btn_middle_disable').html(lang('christmasgiftpop_had_buy'));
                }
                if (parseInt(list[i].num) > 0) {
                    obj.find('.limited').html(lang('christmasgiftpop_today_left', [list[i].buytimes]));
                }                
                if (this.lastDay == 1) {
                    obj.find('.cd').attr('class', 'cd last').html(lang('anniversarypop_last_day'));
                } else {
                    obj.find('.cd').attr('class', 'cd');
                }
            }
        },
        
        setCd: function(cd) {
            if (this.lastDay != 1) {
                xGame.updateCd('christmasgift', cd);
            }
        },
        
        bindAll: function() {
            var that = this;

            that.rewardDom.undelegate('.pop_btn_middle', 'click').delegate('.pop_btn_middle', 'click',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }

                var gid = parseInt($(this).attr('gid'));
                var info = that.rewardData[gid - 1];

                xGame.confirm.show('', lang('christmasgiftpop_buy_confirm', [info.price, info.name]), 2, function () {
                    xGame.confirm.hide();
                    that.buyGift(gid);
                }, function () {
                    xGame.confirm.hide();
                });
            });

            // 查看坐骑和将领的信息
            that.rewardDom.delegate('.reward.horse, .reward.hero', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var $this = $(this);
                var $li = $this.parents('li');

                if ($this.hasClass('horse')) {
                    miscpop.showMarchInfo(that.rewardData[$li.index()]['getvalue' + ($li.children('.reward').index($this) + 1)]);
                } else if ($this.hasClass('hero')) {
                    miscpop.showHeroInfo(that.rewardData[$li.index()]['getvalue' + ($li.children('.reward').index($this) + 1)]);
                }

                $this = null;
                $li = null;
                e.preventDefault();
            });
        },
        
        buildRewardHtml: function(data) {
            var html = '';
            for (var i = 1; i < 5;i ++) {
                if (data['gettype' + i] == 7) { // 坐骑
                    html += '<div class="horse reward reward' + i + ' rtype' + data['gettype' + i] + '">';
                    html += '<span style="background-position:' + helper.getHorsePostion('small', data['getvalue' + i].id) + ';"></span>';
                    html += '<b>x' + data['getnum' + i] + '</b>';
                    html += '</div>';
                } else if (data['gettype' + i] == 13) { // 装备精华
                    html +='<div class="reward reward' + i + ' rtype' + data['gettype' + i] + '">';
                    html +='<span style="background-image:url('+ xGame.essence(data['getvalue' + i].id) +')"></span>';
                    html +='<b>x' + data['getvalue' + i].num + '</b>';
                    html +='</div>';
                } else if (data['gettype' + i] == 17) { // 武将
                    html +='<div class="hero reward reward' + i + ' rtype' + data['gettype' + i] + '">';
                    html +='<span style="background-image:url('+ xGame.avatar(data['getvalue' + i].id) +')"></span>';
                    html +='<b>' + data['getvalue' + i].name + '</b>';
                    html +='</div>';
                } else if (data['gettype' + i] == 18) { // 星魄
                    html +='<div class="reward reward' + i + ' rtype' + data['gettype' + i] + '">';
                    html +='<span class="lord'+ data['getvalue' + i].id +'"></span>';
                    html +='<b>x' + helper.convertWan(data['getvalue' + i].num) + '</b>';
                    html +='</div>';
                } else {
                    html +='<div class="reward reward' + i + ' rtype' + data['gettype' + i] + '">';
                    html +='<span></span>';
                    html +='<b>x' + helper.convertWan(data['getvalue' + i]) + '</b>';
                    html +='</div>';
                }
            }
            return html;
        },
        
        buyGift: function(gid) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                switch(parseInt(res.status)) {
                    case -8:
                        xGame.toast.show(lang('vip_lv_too_low'));
                        break;
                    case -7:
                        xGame.toast.show(lang('christmasgiftpop_fail'));
                        break;
                    case -6:
                        xGame.toast.show(lang('christmasgiftpop_buy_fail'));
                        break;
                    case -5:
                        xGame.goPay(lang('christmasgiftpop_gold_not_enough'));
                        break;
                    case -4:
                        xGame.toast.show(lang('christmasgiftpop_had_sold'));
                        break;
                    case -3:
                        xGame.toast.show(lang('christmasgiftpop_gift_received'));
                        break;
                    case -2:
                        xGame.toast.show(lang('activity_is_end'));
                        break;
                    case -1:
                        xGame.toast.show(lang('christmasgiftpop_gift_fail'));
                        break;
                    case 1:
                        if (that.anim) {
                            that.hideAnim();
                        }
                        
                        that.e.find('.christmasgift_reward_box').remove();
                        if (that.deleteThread) {
                            clearTimeout(that.deleteThread);
                        }
                        that.deleteThread = null;
                        
                        player.updateinfo({
                            'gold' : res.all_gold,
                            'act' : res.all_act,
                            'silver' : res.all_silver
                        });
                        that.lastDay = res.last_day;
                        that.isRefresh = false;
                        that.buildList(res.list);
                        that.setCd(parseInt(res.cd));
                        
                        //xGame.toast.show(lang('buy_success'));
                        that.rewardDown(that.rewardData[gid - 1]);
                        
                        that.playAnim(that.rewardDom.find('#christmasgift_l' + gid));
                        
                        if (that.autoThread) {
                            clearTimeout(that.autoThread);
                            that.autoThread = null;
                        }
                        that.autoThread = setTimeout(function() {
                            if (christmasgiftpop.e) {
                                christmasgiftpop.autoRefresh();
                            }
                        }, 3000);
                        
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };

            loadContents({
                'c': 'christmasgift',
                'm': 'get_reward',
                'id': gid
            },
            Func);
        },
        
        rewardDown: function(data) {
            
            var html = '';
            html += '<div class="christmasgift_reward_box">';
            for (var i = 1;i < 5; i ++) {
                if (data['gettype' + i] == 7) { // 坐骑
                    html += '<span class="reward_down pos' + i + ' rtype' + data['gettype' + i] + '">';
                    html += '<em class="horse" style="background-position:' + helper.getHorsePostion('small', data['getvalue' + i].id) + ';"></em>';
                    html += '<b>x' + data['getnum' + i] + '</b>'; 
                    html += '</span>';
                } else if (data['gettype' + i] == 13) { // 装备精华
                    html += '<span class="reward_down pos' + i + ' rtype' + data['gettype' + i] + '"><em style="background-image:url('+ xGame.essence(data['getvalue' + i].id) +')"></em>x' + data['getvalue' + i].num + '</span>';
                } else if (data['gettype' + i] == 17) { // 武将

                    if (data['getvalue' + i].own_info == null) { // 没有拥有此武将
                        html += '<span class="reward_down pos' + i + ' rtype' + data['gettype' + i] + '"><em style="background-image:url('+ xGame.avatar(data['getvalue' + i].id) +')"></em>' + data['getvalue' + i].name + '</span>';
                    } else {
                        html += '<span class="reward_down pos' + i + ' rtype' + data['getvalue' + i].own_info.type + '"><em></em>x' + helper.convertWan(data['getvalue' + i].own_info.value) + '</span>';

                        if (data['getvalue' + i].own_info.type == 4) {
                            xGame.toast.show(lang('newgiftpop_renew', [3, helper.convertWan(data['getvalue' + i].own_info.value)]));
                        } else if (data['getvalue' + i].own_info.type == 5) {
                            xGame.toast.show(lang('newgiftpop_renew', [2, helper.convertWan(data['getvalue' + i].own_info.value)]));
                        }
                    }
                } else if (data['gettype' + i] == 18) { // 星魄
                    html += '<span class="reward_down pos' + i + ' rtype' + data['gettype' + i] + '"><em class="lord'+ data['getvalue' + i].id +'"></em>x' + helper.convertWan(data['getvalue' + i].num) + '</span>';
                } else {
                    html += '<span class="reward_down pos' + i + ' rtype' + data['gettype' + i] + '"><em></em>x' + helper.convertWan(data['getvalue' + i]) + '</span>';
                }
            }
            html += '</div>';
            this.e.find('#christmasgiftpop_wrap').append(html);
            
            this.deleteThread = setTimeout(function() {
                if (christmasgiftpop.e) {
                    christmasgiftpop.e.find('.christmasgift_reward_box').remove();
                }
            }, 3000);
            
            html = null;
        },
        
        playAnim: function(obj) {
            var that = this;

            var xy_config = {
                1 : [4, 30],
                2 : [96, 30],
                3 : [188, 30],
                4 : [280, 30]
            };

            that.anim = [];
            for (var i = 1; i <= 4; i++) {
                //that.anim[i] = {};
                that.anim[i] = new movieClipClass({
                    "img": staticUrl + 'img/pop/firstpaypop_anim.png',
                    "width": 93,
                    "height": 90,
                    "x": xy_config[i][0],
                    "y": xy_config[i][1],
                    "loop": true,
                    "frames": [{
                        rect: [0, 0],
                        interval: 100
                    },
                    {
                        rect: [93, 0],
                        interval: 100
                    },
                    {
                        rect: [186, 0],
                        interval: 100
                    },
                    {
                        rect: [279, 0],
                        interval: 100
                    },
                    {
                        rect: [0, 90],
                        interval: 100
                    },
                    {
                        rect: [93, 90],
                        interval: 100
                    },
                    {
                        rect: [186, 90],
                        interval: 100
                    }
                    ]
                });
                //开始播放
                that.anim[i].appendTo(obj);
                that.anim[i].play();
            }
        },

        hideAnim: function() {
            if (!this.anim) {
                return;
            }
            for (var i = 1; i <= 4; i++) {
                if (this.anim[i]) {
                    this.anim[i].destory();
                }
            }
            this.anim = null;
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
                        
            if (this.rewardDom) {
                this.rewardDom.undelegate('.pop_btn_gold', 'click');
                this.rewardDom.remove();
                this.rewardDom = null;
            }
            
            if (typeof CD['christmasgift'] != undefined) {
                xGame.updateCd('christmasgift', 0);
            }
            
            if (this.myScroller != null) {
                this.myScroller.destroy();
                this.myScroller = null;
            }
            
            if (this.autoThread) {
                clearTimeout(this.autoThread);
                this.autoThread = null;
            }
            
            if (this.deleteThread) {
                clearTimeout(this.deleteThread);
                this.deleteThread = null;
            }
            
            this.lastDay = null;
            this.rewardData = null;
            this.isRefresh = false;
            this.hideAnim();
            if (this.e) {
                 this.e.find('.hidebtn').unbind('touchend');
                 this.e.remove();
                 this.e = null;
            }
        },
    };
})();