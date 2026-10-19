/* 群雄逐鹿 */
(function() {
    zhuluPopClass = function() {
        this.e;
        this.isshow = false;

        this.lefttimes = 0;
        this.rank; //当前排名
        this.infoPageLoaded = false;
    };
    zhuluPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {
                if(res.status == 1) {
                    that.buildPage(res);
                } else {
                    var errmsg = '';
                    if(res.status == -1) {//未开启
                        errmsg = lang('func_not_open');
                    } else if(res.status == 2) {
                        if (player.info.compete_type == 1) {
                            errmsg = lang('newzhulu_level_limit');
                        } else {
                            errmsg = lang('zhulu_level_limit');
                        }
                    } else {
                        errmsg = lang('undefined_error');
                    }
                    xGame.toast.show(errmsg);
                    $('#worldloading').hide();
                }
            };
            loadContents({
                'c': 'compete',
                'm': 'index'
            },
            initRender, true);
            initRender = null;

        },

        buildPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('zhulu');
            
            if (player.info.compete_type == 1) {
                that.e.addClass('new');
            }

            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                zhulupop.hide();
                e.preventDefault();
            });

            //CD
            xGame.updateCd('zhulu', res.cd);
            //剩余次数
            that.lefttimes = res.log.times;
            that.e.find('#zhulupop_lefttimes').children('span').html(res.log.times);
            //我的排名
            that.e.find('#zhulupop_myrank').children('span').html(res.log.rank);
            //TOPlist
            that.buildTopList(res.top_list);
            //NearList
            that.buildNearlist(res.list);
            //Rewardlist
            that.buildRewardList(res.reward);//, res.cd, res.is_get);
            //fetchBtn
            if(res.cd <= 0) {//活动结束后，按钮可以点
                //1 已领取 0未领取
                that.updateFetchBtn(res.is_get);
            }
            //事件绑定
            that.bindAll();

            that.e.show();

            //$('#zhulupop_info_dom').fadeIn();
            $('#worldloading').hide();

        },

        bindAll: function() {
            var that = this;

            that.e.find('#zhulupop_info_btn').unbind('touchend').bind('touchend', function() {
                that.showInfoPage();
            });
            that.e.find('#zhulupop_buy_btn').unbind('touchend').bind('touchend', function() {
                that.doBuyTimes();
            });
            that.e.undelegate('.pop_btn_middle.fetch_btn', 'touchend').delegate('.pop_btn_middle.fetch_btn', 'touchend', function() {
                that.doFetch();
            });
            that.e.find('#zhulupop_toplist').undelegate('li', 'click').delegate('li', 'click', function() {
                var _uid = $(this).data('uid');
                if(_uid) {
                    xGame.showpop('info', parseInt(_uid));
                }
            });
            that.e.find('#zhulupop_nearlist').undelegate('.click_rect', 'click').delegate('.click_rect', 'click', function() {
                var _uid = $(this).data('uid');
                if(_uid) {
                    xGame.showpop('info', parseInt(_uid));
                }
            });
            that.e.find('#zhulupop_nearlist').undelegate('.attack_btn', 'touchend').delegate('.attack_btn', 'touchend', function() {
                var _uid = $(this).data('uid');
                if(_uid) {
                    that.doAttack(parseInt(_uid));
                }
            });
        },

        buildTopList: function(list) {

            var html = '';
            for(var i = 1; i <= 3; i++) {
                var _user = list[i];
                if(_user) {
                    html += '<li class="zhulu_topthree_li" data-uid="'+_user.uid+'"><span class="zhulu_topthree_name tree'+_user.tree+'">'+_user.nickname+'</span></li>';  
                }
            }

            this.e.find('#zhulupop_toplist').html(html);
        },

        buildNearlist: function(list) {

            var html = '';
            for(var i = 1; i <= 5; i++) {
                var _user = list[i];
                if(typeof _user == 'undefined') {
                    continue;
                }
                html += '<li class="zhulupop_nearlist_item avatar'+_user.gender+'">';
                html +=     '<p class="info1">'+lang('player')+':<span>'+_user.nickname+'</span></p>';
                html +=     '<p class="info2">'+lang('level')+':<span>'+_user.level+'</span></p>';
                if(_user.uid != player.info.uid) {//enemy
                    html += '<p class="info3">'+lang('ranks')+':<span>'+_user.rank+'</span></p>';
                    html +=     '<div class="click_rect" data-uid="'+_user.uid+'"></div>';
                    if(_user.status == 1) {
                        html += '<button class="btn pop_btn_middle attack_btn" data-uid="'+_user.uid+'">'+lang('attack')+'</button>';
                    }
                } else {
                    html += '<div class="rankflag">NO.'+_user.rank+'</div>';
                    html +=     '<div class="click_rect" data-uid="'+_user.uid+'"></div>';
                }
                html += '</li>';
            }
            this.e.find('#zhulupop_nearlist').html(html);
        },

        buildRewardList: function(rewardlist) {

            var html = '';
            if(rewardlist instanceof Array && rewardlist.length == 0) {//200名以外，没有奖励
                html += '<p class="zhulupop_noreward">'+lang('no')+'</p>';
            } else {
                for(var i = 1; i <= 4; i++) {
                    var _type  = rewardlist['type'+i];
                    var _value = rewardlist['value'+i];
                    if(typeof _type == 'undefined' || _value == 0) {
                        continue;
                    }
                    if(_type <= 6) {//普通物资
                        html += '<p class="zhulupop_rewarditem '+this._get_reward_class(_type)+'" id="zhulupop_rewarditem'+i+'">';
                        html += _value;
                        html += '</p>';
                    } else if(_type == 8){//坐骑
                        html += '<p class="zhulupop_rewarditem horse" id="zhulupop_rewarditem'+i+'" style="color:'+helper.getItemColor(_value.quality)+'">';
                        html += '<span class="horse_pic" style="background-position:' + helper.getHorsePostion('buff', _value.id) + ';"></span><span class="horse_name">' + _value.name + '</span>';
                        html += '</p>';
                    } else if(_type == 7) {//道具
                        html += '<p class="zhulupop_rewarditem item" id="zhulupop_rewarditem'+i+'" style="color:'+helper.getItemColor(_value.quality)+'">';
                        html += _value.name;
                        html += '</p>';
                    }
                }
            }
            this.e.find('#zhulupop_rewarditem_wrap').html(html);
        },

        updateFetchBtn: function(getstatus) {

            var fetchBtnWord = getstatus == 1 ? lang('warena_have_fetch') : lang('receive');
            var fetchBtnCls = getstatus == 1 ? 'btn pop_btn_middle_disable fetch_btn' : 'btn pop_btn_middle fetch_btn';
            this.e.find('#fetch_btn').attr('class', fetchBtnCls).html(fetchBtnWord);

        },

        _get_reward_class: function(type) {
            var cls = '';
            var type = parseInt(type);
            switch(type) {
                case 1:
                    cls = 'gold';
                    break;
                case 2:
                    cls = 'silver';
                    break;
                case 3:
                    cls = 'gem3';
                    break;
                case 4:
                    cls = 'tfcard';
                    break;
                case 5:
                    cls = 'osoul';
                    break;
                case 6:
                    cls = 'jade';
                    break;
                case 7:
                    cls = 'item';
                    break;
                case 8:
                    cls = 'horse';
                    break;
            }
            return cls;
        },

        showInfoPage: function() {

            var that = this;

            if(that.infoPageLoaded) {

                that.e.find('#zhulupop_info_dom').show();

            } else {

                var Func = function(res) {
                    if(res.status == 1) {

                        that.infoPageLoaded = true;

                        var infoPage = that.e.find('#zhulupop_info_dom');

                        infoPage.find('#zhulupop_info_hide').unbind('touchend').bind('touchend', function(e) {
                            that.e.find('#zhulupop_info_dom').hide();
                            e.preventDefault();
                        });

                        var html = '';
                        for(var i = 0; i < res.list.length; i++) {

                            html += '<li class="rewardlist_item">';
                            for(var z = 1; z <= 4; z++) {
                                var _type = res.list[i]['type'+z];
                                var _value = res.list[i]['value'+z];
                                if(typeof _type == 'undefined' || _value == 0) {
                                    continue;
                                }
                                html += '<p class="'+that._get_reward_class(_type)+'" style="color:'+helper.getItemColor(_value.quality)+'">';
                                if(_type == 8) {//坐骑
                                    html += '<span class="horse_pic" style="background-position:' + helper.getHorsePostion('buff', _value.id) + ';"></span><span class="horse_name">' + _value.name + '</span>';
                                } else if(_type == 7) {//道具
                                    html += _value.name;
                                } else {
                                    var _value = parseInt(_value);
                                    if(_value > 9999999) {
                                        _value = player.getWan(_value);
                                    }
                                    html += _value;
                                }
                                html += '</p>';
                            }
                            html += '</li>';
                        }
                        infoPage.find('#rewardlist_wrap').html(html);

                        infoPage.show();
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                };
                loadContents({
                    'c': 'compete',
                    'm': 'get_reward_list',
                },
                Func); 
                Func = null;
            }

 

        },

        doFetch: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                res.status = parseInt(res.status);
                if (res.status == 1) {
                    player.updateinfo({
                        "silver": parseInt(res.all_silver),
                        "gold": parseInt(res.all_gold),
                        "act": parseInt(res.all_act),
                    });
                    xGame.toast.show(lang('reward_success'));
                    that.updateFetchBtn(1);
                } else if (res.status == -1) {
                    xGame.toast.show(lang('no_reward'));
                } else if (res.status == -4) {
                    xGame.toast.show(lang('zhulu_not_in_200'));
                } else if (res.status == -5) {
                    xGame.toast.show(lang('had_receive'));
                }else {
                    xGame.toast.show(lang('have_not_begin'));
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'compete',
                'm': 'get_reward'
            },
            Func);
            Func = null;
        },

        doBuyTimes: function() {
            var that = this;

            var priceRender = function(res) {
                if (res.status == 1 && res.price > 0) {
                    xGame.confirm.show(lang('notice'), lang('buy_times_ask', [res.price, that.lefttimes]), 2,
                    function() {
                        that.buytimesProcess();
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('buy'), lang('close'));
                } else {
                    xGame.toast.show(lang('can_not_buy_times_yet'));
                    return false;
                }
            };
            loadContents({
                'c': 'compete',
                'm': 'buy_pk_num',
                't': 0
            },
            priceRender);
            priceRender = null;
        },

        /* 开始购买次数 */
        buytimesProcess: function() {
            var that = this;

            var buyRender = function(res) {
                if (res.status == 1) {
                    player.updateinfo({
                        "gold": res.gold
                    });
                    that.lefttimes = res.times;
                    that.e.find('#zhulupop_lefttimes').children('span').html(res.times);
                    $('#buy_arenatimes_price').html(res.price);
                    $('#buy_arenatimes').html(res.times);
                } else if (res.status == -1) {
                    xGame.goPay(lang('gold_not_enough_for_buying'));
                } else {
                    xGame.toast.show(lang('buy_fail_try_later'));
                }

            };
            loadContents({
                'c': 'compete',
                'm': 'buy_pk_num',
                't': 1
            },
            buyRender);
            buyRender = null;
        },

        doAttack: function(uid) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    that.e.find('#zhulupop_lefttimes').children('span').html(res.info.times);
                    that.lefttimes = res.info.times;

                    that.e.find('#zhulupop_myrank').children('span').html(res.info.rank);

                    that.buildNearlist(res.info.list);
                    that.buildTopList(res.info.top_list);
                    if (res.info.reward_status == 1) {
                        that.buildRewardList(res.info.reward);
                    }

                    miscpop.enterBattleFromZhulu(res, uid);

                    break;
                case - 1 : that.doBuyTimes();
                    break;
                case - 2 :
                    xGame.toast.show(lang('activity_end'));
                    break;
                case - 3 :
                    xGame.toast.show(lang('times_not_enough'));
                    break;
                case - 4 : xGame.toast.show(lang('zhulu_not_join'));
                    break;
                case - 5 : xGame.toast.show(lang('zhulu_in_battle'));
                    break;
                case - 6 : xGame.toast.show(lang('zhulu_cannot_fight_loser'));
                    break;
                default : xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'compete',
                'm': 'action',
                't': uid
            },
            Func);
            Func = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('#zhulupop_info_btn').unbind('touchend');
                this.e.find('#zhulupop_buy_btn').unbind('touchend');
                this.e.find('#zhulupop_info_dom').find('#zhulupop_info_hide').unbind('touchend');
                this.e.undelegate('.pop_btn_middle.fetch_btn', 'touchend');
                this.e.find('#zhulupop_toplist').undelegate('li', 'click');
                this.e.find('#zhulupop_nearlist').undelegate('.click_rect', 'click');
                this.e.find('#zhulupop_nearlist').undelegate('.attack_btn', 'touchend');
                this.e.remove();
                this.e = null;
            }
        },
 
        hide: function() {

            xGame.unlock();

            xGame.updateCd('zhulu', 0);
            this.isshow = false;
            this.lefttimes = 0;
            this.rank;
            this.infoPageLoaded = false;
           
            this.unbindAll();
        },
    };
})();