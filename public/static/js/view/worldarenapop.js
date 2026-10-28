/* 跨服 */
(function() {
    worldarenaPopClass = function() {
        this.e;
        this.isshow = false;
        this.enterDom; //入口dom
        this.prepareDom; //准备dom
        this.prepareListDom;
        this.prepareReportDom;
        this.shopDom; //商店dom
        this.shopTopDom;
        this.shopLeftDom;
        this.shopRightDom;
        this.shopScroller;
        this.top;
        this.bet;
        this.reward;
        this.pages;
        this.page;
        this.reportpage;
        this.reportpages;
        this.shopList;
        this.times;
        this.timePrice;
        //是否是挑战者 用于判断是否可查看资料
        this.isActor = false;
    };
    worldarenaPopClass.prototype = {
        init: function(data) {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        //打开界面
                        that.initFirst(res);
                        if (data == true) {
                            that.fillPrepareDom();
                            that.fillreport();
                        }
                        break;
                    case -4:
                        xGame.toast.show(lang('warena_fail_get_yazhu_target'));
                        $('#worldloading').hide();
                        break;
                    case -3:
                        xGame.toast.show(lang('warena_fail_get_crossserver_info'));
                        $('#worldloading').hide();
                        break;
                    case -2:
                        xGame.toast.show(lang('warena_fail_get_crossserver_reward'));
                        $('#worldloading').hide();
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_fail_get_rank'));
                        $('#worldloading').hide();
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_level_not_enough'));
                        $('#worldloading').hide();
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        $('#worldloading').hide();
                        break;
                    default :
                        xGame.toast.show(lang('warena_not_open'));
                        $('#worldloading').hide();
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'index'
            },
            initRender, true);

            initRender = null;
        },

        initFirst: function(res) {
            var that = this;

            if(this.e == null) {
                that.e = xGame.loadPopTmpl('worldarena');

                that.enterDom = that.e.find('#worldarena_enter');

                that.prepareDom = that.e.find('#worldarena_prepare');
                that.prepareListDom = that.prepareDom.find('#worldarena_list_dom');
                that.prepareReportDom = that.prepareDom.find('#report_dom');

                that.shopDom = that.e.find('#worldarena_shop');
                that.shopTopDom = that.shopDom.find('#worldarena_shop_top');
                that.shopLeftDom = that.shopDom.find('#worldarena_shop_left');
                that.shopRightDom = that.shopDom.find('#worldarena_shop_right');
                that.pages = 0;
                that.page = 0;
                that.reportpage = 0;
                that.reportpages = 50;
                that.times = 0;
                that.timePrice = 0;

                //保存数据
                that.top = res.top || [];
                that.bet = res.bet || {};
                that.reward = res.reward || { rank: 1, exploit: 100, cd: 0 };
                //组装页面数据
                that.fillEnterDom();

                that.bindAll();
            }
            that.e.show();
            $('#worldloading').hide();

        },

        fillEnterDom: function() {
            this.enterDom.find('#worldarena_server').find('span').html(xGame.xsGet('server_name'));
            this.fillTop3();
            this.fillD1();
            this.fillD2();
        },

        fillTop3: function() {
            var that = this;
            var html = '';
            for (var i = 0; i < that.top.length; i++) {
                if (typeof that.top[i].tree != 'undefined' && parseInt(that.top[i].tree) > 1) {
                    html += '<li class="t'+that.top[i].rank+'" uid="'+that.top[i].uid+'" serverid="'+that.top[i].serverid+'"><em class="tree' + that.top[i].tree + '">' +that.top[i].nickname+ '</em></li>';
                } else {
                    html += '<li class="t'+that.top[i].rank+'" uid="'+that.top[i].uid+'" serverid="'+that.top[i].serverid+'">' +that.top[i].nickname+ '</li>';
                }
            }
            this.enterDom.find('ul').html(html);
            html = null;
        },

        fillD1: function() {
            /*TODO*/
            if(this.reward != '') {
                this.enterDom.find('#worldarena_list').html(this.reward.rank);
                this.enterDom.find('#worldarena_list_r').html(this.reward.exploit);
                xGame.updateCd('worldarenareward', this.reward.cd);
            } else {
                this.enterDom.find('#worldarena_list').html(lang('warena_no_access'));
            }
        },

        fillD2: function() {
            /*TODO*/
            if (this.bet != '') {
                this.bet.rank = this.bet.rank || { before: 0, after: 0 };
                if(typeof this.bet.nickname != 'undefined') {
                    this.enterDom.find('#worldarena_text').html(this.bet.nickname.length > 0 ? lang('warena_yazhu_target', [this.bet.nickname]) : lang('warena_yazhu_target', [lang('no')]));
                } else {
                    this.enterDom.find('#worldarena_text').html(lang('warena_your_supportor', [this.bet.support || 0]));
                }
                this.enterDom.find('#worldarena_reward').html(this.bet.silver || 0);
                if(this.bet.rank.before == 0 && this.bet.rank.after == 0) {
                    var str = lang('warena_no_rank');//无
                } else {
                    var str = this.bet.rank.before + ' → '+ this.bet.rank.after;
                }
                if (this.bet.rank.before > this.bet.rank.after) {
                    str += '<em class="green">↑</em>';
                } else if(this.bet.rank.before < this.bet.rank.after) {
                    str += '<em class="red">↓</em>';
                }
                this.enterDom.find('#worldarena_pm').html(str);
                xGame.updateCd('worldarenabet', this.bet.cd);
            } else {
                this.enterDom.find('#worldarena_text').html(lang('warena_your_supportor2'));
            }
        },

        fillPrepareDom: function() {
            var that = this;
            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        //打开界面
                        that.e.find('#worldarenapop_wrap').removeClass('enter').addClass('prepare');
                        //type==1,参与者，type==2，观众
                        if (res.info != '') {
                            that.isActor = true;//是参赛者
                            that.timePrice = res.info.times_price;
                            that.fillFeats(res.info.exploit);
                            that.fillTimes(res.info.times);
                            that.prepareDom.attr('class', 'actor');
                        } else {
                            that.prepareDom.attr('class', 'viewer');
                        }
                        that.fillPlayerList(res.rank);
                        that.fillAllTop3(res.top);
                        break;
                    case -2:
                        xGame.toast.show(lang('warena_fail_get_yazhu_info'));
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_fail_get_rankdata'));
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_level_not_enough'));
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        break;
                    default :
                        xGame.toast.show(lang('warena_not_open'));
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'arena'
            },
            initRender);
            initRender = null;
        },

        fillFeats: function(res) {
            this.prepareDom.find('#gongxun').html(res);
        },

        fillTimes: function(res) {
            this.times = res;
            this.prepareDom.find('#worldarena_times').html(res);
        },

        /*前3名*/
        fillAllTop3: function(data) {
            for(var i = 0; i < data.length; i++) {
                //修改：前3也能看资料
                var topDom = this.prepareDom.find('#worldarena_top'+data[i].rank);
                var attrData = {
                    'class': 'worldarena_top3',
                    'uid': data[i].uid,
                    'serverid': data[i].serverid,
                };
                if(this.isActor == true) {
                    attrData['class'] += ' showinfo';
                }
                if (typeof data[i].tree != 'undefined' && parseInt(data[i].tree) > 1) {
                    attrData['class'] += ' tree';
                    topDom.attr(attrData).html('<em class="tree' + data[i].tree + '"></em>' + lang('warena_x_server', [data[i].groupname+''+data[i].sid])+' '+data[i].nickname);//s1服 solo
                } else {
                    topDom.attr(attrData).html(lang('warena_x_server', [data[i].groupname+''+data[i].sid])+' '+data[i].nickname);//s1服 solo
                }
            }
        },

        /*排行列表*/
        fillPlayerList: function(data) {
            var that = this;
            var html = '';
            var button = '';
            /*type==1,参与者，type==2，观众*/
            for (var i = 0; i < data.list.length; i++) {
                var class_name = '';
                //有字段，说明是参与者
                if (that.isActor == true) {
                    class_name = 'showinfo';
                }
                if(data.list[i].myself == 1) {
                    html += '<li class="me">';
                } else {
                    html += '<li>';
                }
                html += '<em'+(data.list[i].rank > 99 ? ' class="line"' : '')+'>'+data.list[i].rank+'</em>';
                if (typeof data.list[i].tree != 'undefined' && parseInt(data.list[i].tree) > 1) {
                    html += '<b class="' + class_name + ' tree tree' + data.list[i].tree + '" uid="'+data.list[i].uid+'" serverid="'+data.list[i].serverid+'">' + lang('warena_x_server', [data.list[i].groupname+''+data.list[i].sid])+' '+data.list[i].nickname + '</b>';
                } else {
                    html += '<b class="' + class_name + '" uid="'+data.list[i].uid+'" serverid="'+data.list[i].serverid+'">' + lang('warena_x_server', [data.list[i].groupname+''+data.list[i].sid])+' '+data.list[i].nickname + '</b>';
                }
                if(typeof data.list[i].challenge != 'undefined' && data.list[i].challenge == 1) {
                    html += '<span class="atk" uid="'+data.list[i].uid+'" serverid="'+data.list[i].serverid+'"></span>';
                } else if(typeof data.list[i].bet != 'undefined') {
                    if(data.list[i].bet == 1) {
                        html += '<span class="bet" uid="'+data.list[i].uid+'" serverid="'+data.list[i].serverid+'"></span>';
                    } else if (data.list[i].bet == 2) {
                        html += '<span class="isbet"></span>';
                    }
                }
                html += '</li>';
            }
            that.prepareDom.find('#worldarena_ul').html(html);
            that.prepareDom.find('#worldarena_p3').html(data.page);
            that.page = data.page;
            that.pages = data.pages;
        },

        /*排行翻页*/
        pageing: function() {
            var that = this;
            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        that.fillPlayerList(res.rank);
                        that.fillAllTop3(res.top);
                        break;
                    case -2:
                        xGame.toast.show(lang('warena_fail_get_yazhu_info'));
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_fail_get_rankdata'));
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_level_not_enough'));
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        break;
                    default :
                        xGame.toast.show(lang('warena_not_open'));
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'get_rank_list',
                'page': that.page
            },
            initRender);
            initRender = null;
        },


        fillreport: function() {
            var that = this;
            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        that.prepareDom.find('#report_btn').attr('class', 'close');
                        that.prepareDom.find('#report_dom').css('display', 'block');
                        var html = '';
                        for (var i = 0; i < res.list.length; i++) {
                            html += '<li>';
                            if  (typeof res.list[i].fromtree != 'undefined' && parseInt(res.list[i].fromtree) > 1) {
                                html += '<em class="tree tree' + res.list[i].fromtree + '">'+lang('warena_x_server', [res.list[i].fromgroup+''+res.list[i].sid])+'</em><em class="under_name '+ (that.isActor == true ? 'showinfo' : '')+ '" uid="'+res.list[i].fromuid+'" serverid="'+res.list[i].fromserverid+'">'+res.list[i].from+'</em>';
                            } else {
                                html += '<em>'+lang('warena_x_server', [res.list[i].fromgroup+''+res.list[i].sid])+'</em><em class="under_name '+ (that.isActor == true ? 'showinfo' : '')+ '" uid="'+res.list[i].fromuid+'" serverid="'+res.list[i].fromserverid+'">'+res.list[i].from+'</em>';
                            }
                            html += lang('warena_fight');//' 挑战 ';
                            if (typeof res.list[i].totree != 'undefined' && parseInt(res.list[i].totree) > 1) {
                                html += '<em class="tree tree' + res.list[i].totree + '">'+lang('warena_x_server', [res.list[i].togroup+''+res.list[i].tosid])+'</em><em class="under_name '+ (that.isActor == true ? 'showinfo' : '')+ '" uid="'+res.list[i].touid+'" serverid="'+res.list[i].toserverid+'">'+res.list[i].to+'</em>';
                            } else {
                                html += '<em>'+lang('warena_x_server', [res.list[i].togroup+''+res.list[i].tosid])+'</em><em class="under_name '+ (that.isActor == true ? 'showinfo' : '')+ '" uid="'+res.list[i].touid+'" serverid="'+res.list[i].toserverid+'">'+res.list[i].to+'</em>';
                            }
                            if(parseInt(res.list[i].win) == 1) {
                                html += lang('warena_win');//' 胜利了 ';
                            } else {
                                html += lang('warena_lose');//' 失败了 ';
                            }
                            if (parseInt(res.list[i].torank) > 0) {
                                html += lang('warena_rank_up_to', [res.list[i].torank]);//'排名提升至第'+res.list[i].torank+'名！';
                            } else {
                                html += lang('warena_rank_no_change');//'排名无变化！';
                            }
                            html +='<em class="under_line" report="'+res.list[i].id+'">' + lang('warena_view_battle_log') + '</em>';//查看战报
                            html += '</li>';
                        }
                        if(res.list.length >= 5){
                            that.reportpage = res.page;
                        }
                        that.prepareReportDom.find('ul').html(html);
                        that.prepareReportDom.find('#worldarena_rp3').html(res.page);
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_no_battle_log'));//'当前没有跨服战报!'
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_level_not_enough'));//'您的等级不足，暂未跨服远征!'
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        break;
                    default :
                        xGame.toast.show(lang('warena_not_open'));
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'get_report_list',
                'page': that.reportpage
            },
            initRender);
            initRender = null;
        },

        /*商店界面*/
        fillShopDom: function() {
            var that = this;
            var initRender = function(res) {
                //console.log(res);
                switch(parseInt(res.status)) {
                    case 1:
                        //保存数据
                        that.shopList = res.list;
                        that.e.find('#worldarenapop_wrap').removeClass('enter').addClass('shop');
                        that.fillCurrentFeats(res.exploit);
                        that.fillItemList();
                        that.fillLog(res.log);
                        that.fillItemInfo(0);
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_fail_get_itemlist'));
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_level_not_enough'));
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        break;
                    default :
                        xGame.toast.show(lang('warena_not_open'));
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'shop'
            },
            initRender);
            initRender = null;
        },

        fillCurrentFeats: function(data) {
            this.shopDom.find('#current_feats').html(data);
        },

        fillItemList: function() {
            var that = this;
            var html = '';
            var data = that.shopList;
            for (var i = 0; i < data.length; i++) {
                if (i == 0) {
                    html += '<li class="selected" site="'+i+'">';
                } else {
                    html += '<li site="'+i+'">';
                }
                html += '<div class="itempic" style="background-image:url(' + staticUrl + 'img/'+that.getItemImg(data[i].type)+');"></div>';
                html +='<span>'+data[i].name+'</span></li>';
            }

            this.shopTopDom.find('ul').html(html).width(data.length * 140 + 'px');
            this.shopTopDom.find('#worldarena_shop_scroller').width(data.length * 140 + 'px');
            this.shopScroller = new iScroll('worldarena_shop_carousel', {
                snap: 'li',
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            this.shopScroller.refresh();
        },

        fillLog: function(log) {
            var html = '';
            if(log != null) {
                for (var i = 0; i < log.length; i++) {
                    html += '<li><em class="under_line">'+lang('warena_x_server', [log[i].groupname+''+log[i].sid]) + ' ' + log[i].nickname +'</em><br/><em>' + lang('warena_buyaway') + '<em><b>'+this.getItemName(log[i].type)+log[i].name+'</b></li>';
                }
            }
            this.shopLeftDom.find('ul').html(html);
        },

        /* 取跨服道具名称 */
        getItemName: function(type) {
            var item = {
                1: lang('warena_item1'),//'银币'
                2: lang('warena_item2'),//'蓝宝石'
                3: lang('warena_item3'),//'金宝石'
                4: lang('warena_item4'),//'紫宝石'
                5: lang('warena_item5'),//'军令'
                6: lang('warena_item6'),//'初级突飞卡'
                7: lang('warena_item7'),//'中级突飞卡'
                8: lang('warena_item8')//'高级突飞卡'
            };
            type = type != null ? type : 1;
            return item[type];
        },
        /* 取跨服道具图片 */
        getItemImg: function(type) {
            var item = {1:'pop/worshippop_type2.png', 2:'pop/pop_gem1.png', 3:'pop/pop_gem2.png', 4:'pop/pop_gem3.png', 5:'pop/worshippop_type3.png', 6:'pop/worshippop_type4.png', 7:'pop/worshippop_type5.png', 8:'pop/worshippop_type6.png'};
            type = type != null ? type : 1;
            return item[type];
        },

        fillItemInfo: function(site) {
            var that = this;
            var name = '';
            var img = '';
            var style = '';
            var add = '';
            if(that.shopList[site].type == 1) {
                style = 'silver';
                add = ' x ' + lang('member_level');
            } else if(that.shopList[site].type == 2) {
                style = 'gem1';
            } else if(that.shopList[site].type == 3) {
                style = 'gem2';
            } else if(that.shopList[site].type == 4) {
                style = 'gem3';
            } else if(that.shopList[site].type == 5) {
                style = 'act';
            } else if(that.shopList[site].type == 6) {
                style = 'card1';
            } else if(that.shopList[site].type == 7) {
                style = 'card2';
            } else if(that.shopList[site].type == 8) {
                style = 'card3';
            }
            this.shopRightDom.find('#w_shop_item').find('span').css('background', 'url(' + staticUrl + 'img/' + that.getItemImg(that.shopList[site].type) + ') center center no-repeat');
            this.shopRightDom.find('#w_shop_name').html(that.getItemName(that.shopList[site].type) + that.shopList[site].name);
            this.shopRightDom.find('#w_shop_func').html(lang('warena_persion_add')+'：<em class="'+style+'">'+that.shopList[site].add1 + add +'</em><br/>'+lang('warena_all_add')+'：<em class="'+style+'">'+that.shopList[site].add2 + add +'</em>');
            this.shopRightDom.find('#w_shop_des').html(that.shopList[site].description);
            this.shopRightDom.find('#w_shop_price').find('span').html(that.shopList[site].exploit);
            this.shopRightDom.find('.pop_btn_big').attr('id', that.shopList[site].id);
        },

        /*刷新跨服远征入口界面信息*/
        refreshPage: function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var initRender = function(res) {
                xGame.unlock();
                if(that.e == null) {
                    return;
                }
                switch(parseInt(res.status)) {
                    case 1:
                        //保存数据
                        that.top = res.top;
                        that.bet = res.bet;
                        that.reward = res.reward;
                        //组装页面数据
                        that.fillEnterDom();
                        //打开界面
                        break;
                    case -4:
                        xGame.toast.show(lang('warena_fail_get_yazhu_target'));
                        break;
                    case -3:
                        xGame.toast.show(lang('warena_fail_get_crossserver_info'));
                        break;
                    case -2:
                        xGame.toast.show(lang('warena_fail_get_crossserver_reward'));
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_fail_get_rank'));
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_level_not_enough'));
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        break;
                    default :
                        xGame.toast.show(lang('warena_not_open'));
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'index'
            },
            initRender, true);
            initRender = null;
        },

        /*领取排行奖励*/
        get_rank_reward: function() {
            var that = this;
            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        //打开界面
                        xGame.toast.show(lang('warena_reward_fetch_success', [res.add]));
                        that.enterDom.find('#worldarena_d1btn').attr('class', 'btn pop_btn_middle_disable');
                        xGame.updateCd('worldarenareward', res.cd);
                        break;
                    case -4:
                        xGame.toast.show(lang('warena_reward_fetch_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('warena_reward_top300'));
                        break;
                    case -2:
                        xGame.toast.show(lang('warena_reward_fail_get_record'));
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_have_fetch_reward'));
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_level_not_enough'));
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        break;
                    default :
                        xGame.toast.show(lang('warena_fetch_reward_error'));
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'get_rank_reward'
            },
            initRender);
            initRender = null;
        },

        /*领取押注奖励*/
        get_bet_reward: function() {
            var that = this;
            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        //打开界面
                        xGame.toast.show(lang('warena_reward_fetch_bet_success', [res.add]));
                        that.enterDom.find('#worldarena_d2btn').attr('class', 'btn pop_btn_middle_disable');
                        //xGame.updateCd('worldarenabet', res.cd);
                        that.bet = res.bet;
                        that.fillD2();
                        player.updateinfo({
                            'silver': parseInt(res.silver)
                        });
                        break;
                    case -6:
                        xGame.toast.show(lang('warena_bet_fail_to_add_silver'));
                        break;
                    case -5:
                        xGame.toast.show(lang('warena_fail_get_yazhu_target'));
                        break;
                    case -4:
                        xGame.toast.show(lang('warena_fail_get_yazhu_info'));
                        break;
                    case -3:
                        xGame.toast.show(lang('warena_reward_fail_get_silverreward'));
                        break;
                    case -2:
                        xGame.toast.show(lang('warena_reward_fail_get_record'));
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_have_fetch_reward'));
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_level_not_enough'));
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        break;
                    default :
                        xGame.toast.show(lang('warena_fetch_reward_error'));
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'get_bet_reward'
            },
            initRender);
            initRender = null;
        },

        /*进入战斗*/
        enterPk: function(touid, serverid) {
            var that = this;
            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        //更新次数
                        that.fillTimes(res.times);
                        //进入战斗
                        miscpop.enterBattleFromWorldarena(res, touid);

                        break;
                    case -7:
                        xGame.toast.show(lang('warena_fail_get_rankdata'));
                        break;
                    case -6:
                        xGame.toast.show(lang('warena_update_rank_error'));
                        break;
                    case -5:
                        xGame.toast.show(lang('warena_reduce_times_error'));
                        break;
                    case -4:
                        xGame.toast.show(lang('warena_cannot_get_userinfo'));
                        break;
                    case -3:
                        xGame.toast.show(lang('warena_times_not_enough'));
                        break;
                    case -2:
                        xGame.toast.show(lang('warena_member_not_in_ranklist'));
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_fail_get_top3'));
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_level_not_enough'));
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        break;
                    default :
                        xGame.toast.show(lang('warena_not_open'));
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'pk',
                'touid': touid,
                'serverid':serverid
            },
            initRender);
            initRender = null;
        },

        /*查看战报*/
        showReport: function(id) {
            var that = this;
            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        //console.log(res);
                        //进入战斗
                        miscpop.enterBattleFromWorldarena(res);
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_battle_log_not_exist'));
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_level_not_enough'));
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        break;
                    default :
                        xGame.toast.show(lang('warena_not_open'));
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'get_report_info',
                'id': id
            },
            initRender);
            initRender = null;
        },

        /*购买次数*/
        buyTimes: function() {
            var that = this;
            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        //更新次数
                        that.fillTimes(res.times);
                        player.updateinfo({'gold':res.gold});
                        $('#buy_worldarena_price').html(res.times);
                        break;
                    case -3:
                        xGame.toast.show(lang('warena_add_times_error'));
                        break;
                    case -2:
                        xGame.toast.show(lang('warena_reduce_gold_error'));
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_not_in_top3_for_buy_times'));
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_no_gold_for_buy_times'));
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        break;
                    default :
                        xGame.toast.show(lang('warena_buytimes_error'));
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'buy_times'
            },
            initRender);
            initRender = null;
        },

        /*押注*/
        betting: function(touid, serverid, Dom) {
            var that = this;
            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        that.prepareListDom.find('.bet').removeClass('bet');
                        Dom.addClass('isbet');
                        break;
                    case -3:
                        xGame.toast.show(lang('warena_fail_get_yazhu_target'));
                        break;
                    case -2:
                        xGame.toast.show(lang('warena_cache_is_not_cleaned'));
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_member_not_in_ranklist_for_bet'));
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_level_not_enough_for_bet'));
                        break;
                    case 3:
                        xGame.toast.show(lang('warena_have_bet'));
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        break;
                    default :
                        xGame.toast.show(lang('warena_bet_error'));
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'betting',
                'touid': touid,
                'serverid': serverid
            },
            initRender);
            initRender = null;
        },

        /*购买道具*/
        buyItem: function(id) {
            var that = this;
            var initRender = function(res) {
                console.log(res);
                switch(parseInt(res.status)) {
                    case 1:
                        //更新功勋
                        that.fillCurrentFeats(res.exploit);
                        that.fillLog(res.log);
                        //插入log
                        //that.shopLeftDom.find('ul').prepend('<li><em class="under_line">【'+ xGame.xsGet('server_name') +'】'+ player.info.nickname +'</em><br/><em>买走了<em><b>'+that.shopRightDom.find('.item_name_'+id).html()+'</b></li>');
                        xGame.toast.show(lang('warena_buyitem_success'));
                        break;
                    case -3:
                        xGame.toast.show(lang('warena_buyitem_error'));
                        break;
                    case -2:
                        xGame.toast.show(lang('warena_buyitem_reduce_exploit_error'));
                        break;
                    case -1:
                        xGame.toast.show(lang('warena_buyitem_cannot_fetch_item'));
                        break;
                    case 2:
                        xGame.toast.show(lang('warena_level_not_enough_for_buy'));
                        break;
                    case 3:
                        xGame.toast.show(lang('warena_exploit_not_enough_for_buy'));
                        break;
                    case -10:
                        xGame.toast.show(lang('warena_rank_updating'));
                        break;
                    default :
                        xGame.toast.show(lang('warena_buyitem_error'));
                        break;
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'exchange_item',
                'id': id
            },
            initRender);
            initRender = null;
        },

        bindAll: function() {
            var that = this;

            //入口绑定开始
            that.enterDom.undelegate('#worldarena_hidebtn1', 'touchend').delegate('#worldarena_hidebtn1', 'touchend',
            function(e) {
                worldarenapop.hide();
                e.preventDefault();
            });

            that.enterDom.undelegate('#worldarena_enter_d', 'touchend').delegate('#worldarena_enter_d', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if(that.enterDom.find('#worldarena_d2btn').hasClass('pop_btn_middle')) {
                    xGame.confirm.show(lang('notice'), lang('warena_yazhu_not_fetch_confirm'), 1,
                    function() {
                       xGame.confirm.hide();
                    });
                } else {
                    that.fillPrepareDom();
                }
                e.preventDefault();
            });

            that.enterDom.undelegate('#worldarena_shopbtn', 'touchend').delegate('#worldarena_shopbtn', 'touchend',
            function(e) {
                that.fillShopDom();
                e.preventDefault();
            });

            /*进入演武榜*/
            that.enterDom.undelegate('#go_arena', 'touchend').delegate('#go_arena', 'touchend',
            function(e) {
                e.preventDefault();
                that.hide();
                xGame.showpop('arena');
            });

            that.enterDom.undelegate('#worldarena_d1btn.pop_btn_middle', 'touchend').delegate('#worldarena_d1btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.get_rank_reward();
            });

            that.enterDom.undelegate('#worldarena_d2btn.pop_btn_middle', 'touchend').delegate('#worldarena_d2btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.get_bet_reward();
            });
            //入口绑定结束

            //准备页开始
            that.prepareDom.undelegate('#worldarena_hidebtn2', 'touchend').delegate('#worldarena_hidebtn2', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#worldarenapop_wrap').removeClass('prepare').addClass('enter');
                that.refreshPage();
                if (that.prepareDom.find('#report_btn').hasClass('close')) {
                    that.prepareDom.find('#report_btn').attr('class', 'open');
                    that.prepareDom.find('#report_dom').css('display', 'none');
                }
            });

            that.prepareDom.undelegate('#worldarena_buy_times', 'touchend').delegate('#worldarena_buy_times', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                //console.log('点击了购买');
                xGame.confirm.show(lang('warena_buyconfirm_title'), lang('warena_buyconfirm_content', [that.timePrice, that.times]), 2,
                function() {
                    that.buyTimes();
                }, function() {
                   xGame.confirm.hide();
                });
            });
            
            that.prepareDom.undelegate('.worldarena_top3.showinfo', 'touchend').delegate('.worldarena_top3.showinfo', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var url = islocal ? 't' : 's';
                url = 'http://' + url + $(this).attr('serverid') + '.game.' + xGame.server_domain + '/index.php?v=' + localStorage['sg_ver'] + '&diy=5';
                xGame.showpop('info', {'uid' : parseInt($(this).attr('uid')), 'url' : url});
                e.preventDefault();
                return false;
            });

            that.prepareListDom.undelegate('.showinfo', 'touchend').delegate('.showinfo', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var url = islocal ? 't' : 's';
                url = 'http://' + url + $(this).attr('serverid') + '.game.' + xGame.server_domain + '/index.php?v=' + localStorage['sg_ver'] + '&diy=5';
                xGame.showpop('info', {'uid' : parseInt($(this).attr('uid')), 'url' : url});
                e.preventDefault();
                return false;
            });
            
            that.prepareListDom.undelegate('.atk', 'touchend').delegate('.atk', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.enterPk($(this).attr('uid'), $(this).attr('serverid'));
            });

            that.prepareListDom.undelegate('.bet', 'touchend').delegate('.bet', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var uid = $(this).attr('uid');
                var serverid = $(this).attr('serverid');
                var dom = $(this);
                xGame.confirm.show(lang('warena_yazhu_confirm_notice'), lang('warena_yazhu_confirm_content'), 2, function() {
                    xGame.confirm.hide(function() {
                        that.betting(uid, serverid, dom);
                    });
                }, function() {
                    xGame.confirm.hide();
                });
            });

            that.prepareListDom.undelegate('#worldarena_p1', 'touchend').delegate('#worldarena_p1', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if(that.page != 1) {
                    that.page = 1;
                    that.pageing();
                }
                //console.log('点击了第一页');
            });

            that.prepareListDom.undelegate('#worldarena_p2', 'touchend').delegate('#worldarena_p2', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if(that.page > 1) {
                    that.page = that.page - 1;
                    that.pageing();
                }
                //console.log('点击了上一页');
            });

            that.prepareListDom.undelegate('#worldarena_p4', 'touchend').delegate('#worldarena_p4', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if(that.page < that.pages) {
                    that.page = that.page + 1;
                    that.pageing();
                }
                //console.log('点击了下一页');
            });

            that.prepareListDom.undelegate('#worldarena_p5', 'touchend').delegate('#worldarena_p5', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if(that.page != that.pages) {
                    that.page = that.pages;
                    that.pageing();
                }
                //console.log('点击了最后一页');
            });

            that.prepareReportDom.undelegate('.showinfo', 'touchend').delegate('.showinfo', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var url = islocal ? 't' : 's';
                url = 'http://' + url + $(this).attr('serverid') + '.game.' + xGame.server_domain + '/index.php?v=' + localStorage['sg_ver'] + '&diy=5';
                xGame.showpop('info', {'uid' : parseInt($(this).attr('uid')), 'url' : url});
                e.preventDefault();
                return false;
            });
            that.prepareReportDom.undelegate('#worldarena_rp1', 'touchend').delegate('#worldarena_rp1', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if(that.reportpage != 1) {
                    that.reportpage = 1;
                    that.fillreport();
                }
                //console.log('点击了第一页');
            });

            that.prepareReportDom.undelegate('#worldarena_rp2', 'touchend').delegate('#worldarena_rp2', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if(that.reportpage > 1) {
                    that.reportpage = that.reportpage - 1;
                    that.fillreport();
                }
                //console.log('点击了上一页');
            });

            that.prepareReportDom.undelegate('#worldarena_rp4', 'touchend').delegate('#worldarena_rp4', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if(that.reportpage < that.reportpages) {
                    that.reportpage = that.reportpage + 1;
                    that.fillreport();
                }
                //console.log('点击了下一页');
            });

            that.prepareReportDom.undelegate('#worldarena_rp5', 'touchend').delegate('#worldarena_rp5', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if(that.reportpage != that.reportpages) {
                    that.reportpage = that.reportpages;
                    that.fillreport();
                }
                //console.log('点击了最后一页');
            });
        
            that.prepareDom.undelegate('#report_btn', 'touchend').delegate('#report_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if ($(this).hasClass('open')) {
                    that.fillreport();
                } else if ($(this).hasClass('close')) {
                    $(this).attr('class', 'open');
                    that.prepareDom.find('#report_dom').css('display', 'none');
                    that.reportpage = 0;
                    that.reportpages = 0;
                }
            });

            //准备页结束

            //本服前3资料查看
            that.enterDom.find('ul').undelegate('li', 'touchend').delegate('li', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if ($(this).attr('uid') != undefined) {
                    xGame.showpop('info', $(this).attr('uid'));
                }
            });

            //商店页开始
            that.shopDom.undelegate('#worldarena_hidebtn3', 'touchend').delegate('#worldarena_hidebtn3', 'touchend',
            function(e) {
                e.preventDefault();
                that.e.find('#worldarenapop_wrap').removeClass('shop').addClass('enter');
            });

            that.shopTopDom.undelegate('li', 'click').delegate('li', 'click',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('selected')) {
                    return false;
                }
                that.shopTopDom.find('.selected').removeClass('selected');
                $(this).addClass('selected');
                that.fillItemInfo($(this).attr('site'));
            });

            that.shopRightDom.undelegate('.pop_btn_big', 'click').delegate('.pop_btn_big', 'click',
            function(e) {
                if (xGame.checkLock(true) || $(this).hasClass('selected')) {
                    return false;
                }
                that.buyItem($(this).attr('id'));
                e.preventDefault();
                e.stopPropagation();
            });

            //商店页结束

            //查看战报
            that.prepareReportDom.find('ul').undelegate('.under_line', 'touchend').delegate('.under_line', 'touchend',
            function() {
                miscpop.viewBattleFromWorldarena($(this).attr('report'));
            });

            //查看玩法说明
            that.enterDom.find('#worldarena_description').unbind('touchend').bind('touchend', function() {
                that.e.find('.worldarena_desc').show();
                that.e.find('.worldarena_desc').find('.worldarena_backbtn').unbind('touchend').bind('touchend', function() {
                    that.e.find('.worldarena_desc').hide();
                });
            });
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.worldarena_desc').find('.worldarena_backbtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.enterDom) {
                this.enterDom.find('#worldarena_description').unbind('touchend');
                this.enterDom.undelegate('#worldarena_hidebtn1', 'touchend');
                this.enterDom.undelegate('#worldarena_enter_d', 'touchend');
                this.enterDom.undelegate('#worldarena_shopbtn', 'touchend');
                this.enterDom.undelegate('#go_arena', 'touchend');
                this.enterDom.undelegate('#worldarena_d1btn.pop_btn_middle', 'touchend');
                this.enterDom.undelegate('#worldarena_d2btn.pop_btn_middle', 'touchend');
                this.enterDom.find('ul').undelegate('li', 'touchend');
                this.enterDom.remove();
                this.enterDom = null;
            }
            if (this.prepareDom) {
                this.prepareDom.undelegate('#worldarena_hidebtn2', 'touchend');
                this.prepareDom.undelegate('#worldarena_buy_times', 'touchend');
                this.prepareDom.undelegate('.worldarena_top3.showinfo', 'touchend');
                this.prepareDom.undelegate('#report_btn', 'touchend');
                this.prepareDom.remove();
                this.prepareDom = null;
            }
            if (this.prepareListDom) {
                this.prepareListDom.undelegate('.showinfo', 'touchend');
                this.prepareListDom.undelegate('.atk', 'touchend');
                this.prepareListDom.undelegate('.bet', 'touchend');
                this.prepareListDom.undelegate('#worldarena_p1', 'touchend');
                this.prepareListDom.undelegate('#worldarena_p2', 'touchend');
                this.prepareListDom.undelegate('#worldarena_p4', 'touchend');
                this.prepareListDom.undelegate('#worldarena_p5', 'touchend');
                this.prepareListDom.remove();
                this.prepareListDom = null;
            }
            if (this.prepareReportDom) {
                this.prepareReportDom.undelegate('.showinfo', 'touchend');
                this.prepareReportDom.undelegate('#worldarena_rp1', 'touchend');
                this.prepareReportDom.undelegate('#worldarena_rp2', 'touchend');
                this.prepareReportDom.undelegate('#worldarena_rp4', 'touchend');
                this.prepareReportDom.undelegate('#worldarena_rp5', 'touchend');
                this.prepareReportDom.find('ul').undelegate('.under_line', 'touchend');
                this.prepareReportDom.remove();
                this.prepareReportDom = null;
            }
            if (this.shopDom) {
                this.shopDom.undelegate('#worldarena_hidebtn3', 'touchend');
                this.shopDom.remove();
                this.shopDom = null;
            }
            if (this.shopTopDom) { 
                this.shopTopDom.undelegate('li', 'click');
                this.shopTopDom.remove();
                this.shopTopDom = null;
            }
            if (this.shopRightDom) {
                this.shopRightDom.undelegate('.pop_btn_big', 'click');
                this.shopRightDom.remove();
                this.shopRightDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            xGame.updateCd('worldarenabet', 0);
            xGame.updateCd('worldarenareward', 0);
            
            this.unbindAll();
            if(this.shopScroller) {
                this.shopScroller.destroy();
                delete this.shopScroller;
            }
            
            this.top = null;
            this.bet = null;
            this.reward = null;
            this.pages = null;
            this.page = null;
            this.reportpage = null;
            this.reportpages = null;
            this.shopList = null;
            this.times = null;
            this.timePrice = null;
            this.isActor = false;
        },
    };
})();