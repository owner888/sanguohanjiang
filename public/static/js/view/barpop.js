/*酒馆*/
(function() {
    barPopClass = function() {
        this.e;

        this.page; //当前页码
        this.perPage; //基本不变了,至少9个
        this.totals; //总条目
        this.pages; //总页数
        this.stype; //类型，1为征服榜 2为名将榜
        this.heroData; //当前保存的数据，以武将ID为键
        this.sgid; //当前选中的武将id

        this.cardData; //当前保存的数据，以武将ID为键
        this.scid; //当前选中的突飞卡id
        this.batchBuy = false; //是否是批量购买

        this.heroListDom;
        this.heroDataDom;
        this.gemDom;
        this.heroScroll;

        this.num; //有效条数
        this.showNum; //显示条数
        this.moreTime; //请求次数数量

        this.gem1; //宝石123
        this.gem2;
        this.gem3;

        this.skillScroll = null;
        
    };
    barPopClass.prototype = {
        init: function() {
            var that = this;

            /*初始化变量*/
            that.page = 1;
            that.perPage = 9;
            that.gem1 = that.gem2 = that.gem3 = 0;
            that.showNum = 4;
            that.heroData = {};
            that.cardData = {};
            
            //小于150级还是老的酒馆
            if (parseInt(player.info.level) < 150) {
                that.stype = 1;
            } else {
                that.stype = 4;//todo
            }
            that.listMain();
        },

        initList: function(data) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('bar');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                barpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.e.find('.content').find('#goto_trade').unbind('touchend').bind('touchend',
            function() {
                that.hide();
                xGame.showpop('trade');
            });

            /*基本的赋值*/
            that.heroListDom = that.e.find('#hero_list_scroller');
            that.heroDataDom = that.e.find('#barpop_hero_info');

            that.heroListDom.html(that.fillHeroList(data.list, data.totals));

            that.heroScroll = new iScroll('barpop_scroller', {
                bounce: false,
                hScrollbar: false,
                vScrollbar: false
            });
            that.heroListDom.css('width', (195 * that.num) + 'px');
            that.heroScroll.refresh();

            /*给宝石赋值*/
            that.gemDom = that.e.find('#gem_list');

            that.showGem(1, data.jade[1]);
            that.showGem(2, data.jade[2]);
            that.showGem(3, data.jade[3]);
            
            that.bindAll();
            
            if (parseInt(player.info.level) >= 150) {
                that.e.find('#barpop_wrap').addClass('newbar');
                that.showReputation(data.reputation);
                that.e.find('#hero_list').attr('class', 'hero_list sj');
            } else {
                that.e.find('#hero_list').attr('class', 'hero_list zf');
            }
            
            /* 最后 展示弹窗 */
            that.e.show();
        },
        
        showReputation: function(num) {
            this.gemDom.find('#reputation').html(num);
        },
        
        bindAll: function() {
            var that = this;
            
            that.heroListDom.undelegate('li.hero_detail', 'click').delegate('li.hero_detail', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).attr('gid') != undefined) {
                    that.heroListDom.find('#barGid' + that.sgid).removeClass('selected');
                    that.sgid = $(this).attr('gid');
                    that.heroListDom.find('#barGid' + that.sgid).addClass('selected');
                    that.fillHeroData(that.sgid);
                } else {
                    that.heroListDom.find('#barCid' + that.scid).removeClass('selected');
                    that.scid = $(this).attr('cid');
                    that.heroListDom.find('#barCid' + that.scid).addClass('selected');
                    that.fillCardData(that.scid);
                }
            });

            that.heroListDom.undelegate('li#barpop_more', 'touchend').delegate('li#barpop_more', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                $(this).remove();
                that.num -= 1;
                that.getMore();
            });

            that.e.find('#hero_list').undelegate('.pop_btn_yellow', 'touchend').delegate('.pop_btn_yellow', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.stype == $(this).attr('stype')) { //不设置属性
                    return false;
                }
                that.stype = $(this).attr('stype');
                that.page = 1;
                that.e.find('#hero_list').attr('class', 'hero_list ' + $(this).attr('type'));
                that.listMain('rebuild');
            });

            that.heroDataDom.undelegate('#buy_hero.pop_btn_middle', 'touchend').delegate('#buy_hero.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }

                var obj = $(this);
                if (that.stype != 3) {
                    var gid = that.sgid;
                    var price = that.e.find('#bar_herocost').html();
                    var price_class = that.e.find('#bar_herocost').attr('class');
                    var name = that.heroListDom.find('#barGid' + gid).find('.hero_detail_name').html();
                    
                    var ask_msg = '';
                    if (that.stype == 4) {
                        ask_msg = lang('newbar_buy_hero_ask', [price_class, price, name, that.heroDataDom.find('#bar_reputation').html()]);
                    } else {
                        ask_msg = lang('buy_hero_ask', [price_class, price, name]);
                    }
                    xGame.confirm.show(lang('notice'), ask_msg, 2,
                    function() {
                        xGame.confirm.hide();
                        that.buyHero(obj);
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('ok'), lang('close'));
                } else {
                    var gid = that.scid;
                    that.buyHero(obj);
                }
            });

            that.heroDataDom.undelegate('#batch_buy.pop_btn_middle', 'touchend').delegate('#batch_buy.pop_btn_middle', 'touchend',
            function() {                
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }

                var obj = $(this);
                that.batchBuy = true;
                that.buyHero(obj);
            });

            // 点击技能描述时弹出详细描述弹窗
            that.e.delegate('#ab_line2', 'touchstart', function () {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#bar_skillcontent > .center').html(that.heroDataDom.find('#bar_scroll_skill .bl').html());
                that.e.find('#bar_skillcontent').show();
            }).bind('touchend', function () {
                that.e.find('#bar_skillcontent').hide();
            });
        },

        fillHeroData: function(id) {
            var that = this;
            var hero = (that.heroData && that.heroData[id]) ? that.heroData[id] : {};
            var data = hero.info || {
                name: '三国名将',
                type: 1,
                wuli: 80,
                zhili: 80,
                tili: 80,
                star: 3,
                talent: { name: '勇武', description: '战意昂扬' },
                skill: { name: '破阵', description: '战法暴击' }
            };
            data.talent = data.talent || { name: '勇武', description: '战意昂扬' };
            data.skill = data.skill || { name: '破阵', description: '战法暴击' };
            that.heroDataDom.find('#barpop_vname').html(data.name).attr('class', 'vname' + data.name.length);
            that.heroDataDom.find('#user_ab').attr('class', (data.type == 1 ? 'wj': 'ms') + ' user_ab');
            that.heroDataDom.find('#bar_wuli').html(data.wuli);
            that.heroDataDom.find('#bar_zhili').html(data.zhili);
            that.heroDataDom.find('#bar_tili').html(data.tili);
            that.heroDataDom.find('#bar_hero_star').width((parseInt(data.star) * 18) + 'px');
            that.heroDataDom.find('#ab_line1').html('<em>'+lang('tianfu')+'-' + data.talent.name + '：</em>' + data.talent.description);
            that.heroDataDom.find('#ab_line2 .skill_name').html(lang('skill')+'-' + data.skill.name + '：');
            that.heroDataDom.find('#bar_scroll_skill .bl').html(data.skill.description);
            that.heroDataDom.find('#bar_herocost').attr('class', 'gem' + (hero.paytype || 1)).html(hero.price || 10);
            that.heroDataDom.attr('class','notcard fourL');
            if (that.stype == 4) {
                that.heroDataDom.addClass('sj');
                that.heroDataDom.find('#bar_reputation').html(hero.need_reputation || 0);
            } else {
                that.heroDataDom.removeClass('sj');
            }
            

            if (that.heroData[id].isown == 1 || that.heroData[id].open == 0) {
                that.heroDataDom.find('#buy_hero').attr('class', 'btn pop_btn_middle_disable');
                if (that.heroData[id].isown == 1) {
                    that.heroDataDom.find('#buy_hero').html(lang('hero_had_buy'));
                } else {
                    that.heroDataDom.find('#buy_hero').html(lang('win_can_buy'));
                }
            } else {
                that.heroDataDom.find('#buy_hero').attr('class', 'btn pop_btn_middle').html(lang('buy_hero'));;
            }

            if (that.skillScroll == null) {
                that.skillScroll = new iScroll('bar_scroll_skill', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                });   
            }

            that.skillScroll.refresh();
            that.skillScroll.scrollTo(0, 0);
        },

        buyHero: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : 
                    xGame.toast.show(lang('add_hero_fail'));
                    break;
                case - 2 : 
                    xGame.toast.show(lang('newbar_un_win'));
                    break;
                case - 3 : 
                    xGame.toast.show(lang('newbar_reputation_not_enough'));
                    break;
                case - 4 : 
                    xGame.toast.show(lang('newbar_reputation_fail'));
                    break;
                case 2:
                    if (that.stype != 3) {
                        xGame.toast.show(lang('buy_hero_fail'));
                    } else {
                        xGame.toast.show(lang('buy_fail'));
                    }
                    break;
                case 3:
                    xGame.toast.show(lang('jade_not_enough_for_hero'));
                    break;
                case 4:
                    xGame.toast.show(lang('had_this_hero'));
                    break;
                case 5:
                    xGame.toast.show(lang('barpop_lv_too_low'));
                    break;
                case 1:
                    if (that.stype != 3) {
                        /*购买成功,先修改dom,修改宝石*/
                        obj.attr('class', 'pop_btn_middle_disable btn').html(lang('hero_had_buy'));
                        that.heroListDom.find('#barGid' + that.sgid).append('<span class="hero_detail_own" id="bar_hero_status"></span>');
                        that.heroData[that.sgid].isown = 1;
                        that.showGem(res.type, res.jade);
                        miscpop.newhero(res.general);
                        if (typeof CD['chat'] != 'undefined') {
                            CD['chat'].read();//手动请求一次，为了早点广播
                        }
                    } else {
                        /*购买成功,先修改dom,修改宝石*/
                        if (res.jade < 1000) { //处理各种情况的按钮变灰
                            if (obj.attr('id') == 'batch_buy') {
                                obj.attr('class', 'pop_btn_middle_disable btn');
                            }
                            if(obj.attr('id') == 'buy_hero') {
                                obj.siblings('button').attr('class', 'pop_btn_middle_disable btn');
                            }
                            if(res.jade < 10) {
                                obj.attr('class', 'pop_btn_middle_disable btn');
                                obj.siblings('button').attr('class', 'pop_btn_middle_disable btn');
                            }
                        }
                        that.showGem(res.type, res.jade);
                        //显示购买的突飞卡
                        var cardNum = that._convCardType2Num(res.type, res.price);
                        xGame.toast.show(lang('barpop_buycard_success', [cardNum]));
                    }
                    
                    player.updateinfo({
                        'reputation': res.all_reputation
                    });
                    that.showReputation(res.all_reputation);

                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            if (that.stype != 3) {
                loadContents({
                    'c': 'tavern',
                    'm': 'buy',
                    'generalid': that.sgid
                },
                Func);
            } else {
                if(that.batchBuy == true) {
                    loadContents({
                        'c': 'tavern',
                        'm': 'buy',
                        'generalid': that.scid,
                        'num': 100,
                    },
                    Func);
                    that.batchBuy = false;
                } else {
                    loadContents({
                        'c': 'tavern',
                        'm': 'buy',
                        'generalid': that.scid
                    },
                    Func);
                }                
            }

            Func = null;
        },

        _convCardType2Num: function(cardType, cardPrice) {
            var cardNum = 1;//默认1
            if(cardType == 1 && cardPrice == 1000) {
                cardNum = 100;
            } else if(cardType == 2) {
                cardNum = 2;
            } else if(cardType == 3) {
                cardNum = 4;
            }
            return cardNum;
        },

        listMain: function(type) {
            var that = this;

            var initRender = function(res) {
                switch (parseInt(res.status)) {
                case - 3 : xGame.toast.show(lang('bar_no_hero_info'));
                    $('#worldloading').hide();
                    break;
                case - 2 : xGame.toast.show(lang('get_had_hero_list_fail'));
                    $('#worldloading').hide();
                    break;
                case - 1 : xGame.toast.show(lang('get_hero_price_fail'));
                    $('#worldloading').hide();
                    break;
                case 1:
                    that.totals = parseInt(res.totals);
                    if (type == undefined) {
                        that.num = 0;
                        that.pages = (that.totals % that.perPage == 0 ? that.totals / that.perPage: Math.ceil(that.totals / that.perPage));
                        that.moreTime = 0;
                        that.initList(res);
                    } else if (type == 'more') {
                        that.buildMore(res);
                    } else if (type == 'rebuild') {
                        that.num = 0;
                        that.pages = (that.totals % that.perPage == 0 ? that.totals / that.perPage: Math.ceil(that.totals / that.perPage));
                        that.moreTime = 0;
                        that.heroListDom.html('');
                        if (that.stype != 3) {
                            that.heroDataDom.attr('class', 'notcard');

                            //隐藏批量购买按钮
                            that.heroDataDom.find('#batch_buy').hide();
                            that.heroDataDom.find('.batch_cost').hide();
                            //还原价格的样式
                            that.heroDataDom.find('.bar_herocost').removeClass('isbatch');

                            that.heroListDom.html(that.fillHeroList(res.list, res.totals));
                        } else {
                            that.heroDataDom.attr('class', 'card');
                            that.heroListDom.html(that.fillCard(res.list, res.totals));

                            //修改价格的样式
                            that.heroDataDom.find('.bar_herocost').addClass('isbatch');
                        }
                        that.heroListDom.css('width', that.num * 195 + 'px');
                        that.heroScroll.refresh();
                        barpop.heroScroll.scrollTo(0, 0);
                        that.heroScroll.currPageX = 0;
                        that.showNum = 4;
                    }
                    break;
                case 5:
                    xGame.toast.show(lang('barpop_lv_too_low'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                    break;
                }

                xGame.unlock();
                $('#worldloading').hide();

            };
            if (xGame.checkLock()) {
                return false;
            }

            if (type == undefined) {
                loadContents({
                    'c': 'tavern',
                    'm': 'get_list',
                    'page': that.page,
                    'perpage': that.perPage,
                    'tab': that.stype
                },
                initRender, true); //加载数据
            } else {
                loadContents({
                    'c': 'tavern',
                    'm': 'get_list',
                    'page': that.page,
                    'perpage': that.perPage,
                    'tab': that.stype
                },
                initRender);
            }
            initRender = null;

        },

        fillCard: function(data, totals) {
            var that = this;
            totals = 3;

            var html = '';
            var arr = ['', lang('card1'), lang('card2'), lang('card3')];
            for (var i = 1; i <= totals; i++) {
                if (data[i] != undefined) {
                    if (i == 1 && that.heroListDom.find('li.selected').size() == 0) {
                        html += '<li class="hero_detail selected" id="barCid' + data[i].generalid + '" cid="' + data[i].generalid + '">';
                        that.scid = data[i].generalid;
                    } else {
                        html += '<li class="hero_detail" id="barCid' + data[i].generalid + '" cid="' + data[i].generalid + '">';
                    }

                    html += '<span class="barcard barpop_card' + i + '"></span>';
                    html += '<span class="hero_detail_light"></span>';
                    html += '<span class="hero_detail_name card">' + arr[i] + '</span>';
                    html += '</li>';
                    that.num += 1;
                    that.cardData[data[i].generalid] = data[i];
                    that.cardData[data[i].generalid]['name'] = arr[i];
                }
            }
            that.fillCardData(that.scid);
            return html;
        },

        _convCardId2Num: function(cardId) {
            var cardGetNum = 1;//默认1
            if(cardId == 1001) {
                cardGetNum = 2;
            } else if(cardId == 1002) {
                cardGetNum = 4;
            }
            return cardGetNum;
        },

        fillCardData: function(id) {
            var that = this;
            var data = that.cardData[id];
            that.heroDataDom.find('#barpop_vname').html(data.name).attr('class', 'vname' + data.name.length);
            that.heroDataDom.find('#card_des').html(lang('can_free_times', [data.name, data.times]));
            that.heroDataDom.find('#bar_herocost').attr('class', 'gem' + data.paytype).html(data.price);

            //添加批量购买
            var cardGetNum = that._convCardId2Num(id);//默认1
            that.heroDataDom.find('#buy_hero').html(lang('barpop_batch_buycard', [cardGetNum]));
            if(id == 1000) { //只有是蓝宝石时才有批量购买
                that.heroDataDom.find('.batch_cost').show();
                that.heroDataDom.find('#batch_cost').attr('class', 'gem' + data.paytype).html(data.price * 100);
                that.heroDataDom.find('#batch_buy').show();
            } else {
                that.heroDataDom.find('#batch_buy').hide();
                that.heroDataDom.find('.batch_cost').hide();
            }
            
            if (that['gem' + data.paytype] < data.price) {
                that.heroDataDom.find('#buy_hero').attr('class', 'btn pop_btn_middle_disable');
            } else {
                that.heroDataDom.find('#buy_hero').attr('class', 'btn pop_btn_middle');
            }
            if (that['gem' + data.paytype] < (data.price * 100)) {
                that.heroDataDom.find('#batch_buy').attr('class', 'btn pop_btn_middle_disable');
            } else {
                that.heroDataDom.find('#batch_buy').attr('class', 'btn pop_btn_middle');
            }
        },

        getMore: function() {
            var that = this;
            /*再加一个锁，防止重复刷接口*/
            that.moreTime++;
            if (that.moreTime >= that.pages) {
                return;
            }
            that.page++;
            that.listMain('more');
        },

        buildMore: function(data) {
            var that = this;
            that.heroListDom.append(that.fillHeroList(data.list, data.totals));
            that.heroListDom.css('width', that.num * 195 + 'px');
            that.heroScroll.refresh();
            //that.heroScroll.scrollToPage('next', 0);
        },

        fillHeroList: function(data, totals) {
            var that = this;

            var html = '';

            for (var i = 1; i <= totals; i++) {
                if (data[i] != undefined) {
                    data[i].info = data[i].info || {
                        image: 'hero_' + data[i].generalid,
                        type: 1,
                        name: data[i].name || '名将',
                    };
                    if (i == 1 && that.heroListDom.find('li.selected').size() == 0) {
                        html += '<li class="hero_detail selected" id="barGid' + data[i].generalid + '" gid="' + data[i].generalid + '">';
                        that.sgid = data[i].generalid;
                    } else {
                        html += '<li class="hero_detail" id="barGid' + data[i].generalid + '" gid="' + data[i].generalid + '">';
                    }
                    html += '<img src="' + xGame.avatar(data[i].info.image, true) + '" />';
                    html += '<span class="hero_detail_type">' + helper.getItemVocation(data[i].info.type) + '</span>';
                    html += '<span class="hero_detail_light"></span>';
                    html += '<span class="hero_detail_name">' + data[i].info.name + '</span>';
                    if (data[i].open != undefined && data[i].open == 0) {
                        html += '<span class="hero_detail_closed" id="bar_hero_status"></span>';
                    }
                    if (data[i].isown == 1) {
                        html += '<span class="hero_detail_own" id="bar_hero_status"></span>';
                    }
                    html += '</li>';
                    that.num += 1;
                    that.heroData[data[i].generalid] = data[i];
                }
            }
            /*
            if(that.page * that.perPage < that.totals){
                html += '<li id="barpop_more"><span>？<b>点击查看更多</b></span></li>';
                that.num += 1;
            }
            */
            that.fillHeroData(that.sgid);
            return html;
        },

        showGem: function(gemid, num) {
            var that = this;

            num = parseInt(num);

            that['gem' + gemid] = num;

            that.gemDom.find('#gem' + gemid).html(num);
        },

        unbindAll: function() {
            this.e.find('.content').find('.hidebtn').unbind('touchend');
            this.e.find('.content').find('#goto_trade').unbind('touchend');
            this.e.find('#hero_list').undelegate('.pop_btn_yellow', 'touchend');
            this.heroListDom.undelegate('li.hero_detail', 'click');
            this.heroListDom.undelegate('li#barpop_more', 'touchend');
            this.heroListDom.remove();
            this.heroDataDom.undelegate('#buy_hero.pop_btn_middle', 'touchend');
            this.heroDataDom.undelegate('#batch_buy.pop_btn_middle', 'touchend');
            this.heroDataDom.remove();
            this.e.undelegate('#ab_line2', 'touchstart');
            this.e.unbind('touchend');
        },

        hide: function() {
            xGame.unlock();
            this.unbindAll();

            this.stype = this.heroData = this.sgid = null;
            this.heroDataDom = null;
            this.heroListDom = null;
            if (this.gemDom) {
                this.gemDom.remove();
                this.gemDom = null;
            }
            if (this.heroScroll) {
                this.heroScroll.destroy();
                delete this.heroScroll;
            }

            if (this.skillScroll) {
                this.skillScroll.destroy();
                this.skillScroll = null;
            }
            this.page = this.perPage = this.gem1 = this.gem2 = this.gem3 = this.num = this.moreTime = this.showNum = this.totals = this.pages = this.cardData = this.scid = null;
            this.batchBuy = false; 
            this.e.remove();
            this.e = null;
        },
    };
})();