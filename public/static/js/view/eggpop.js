/* 砸金蛋 */
(function() {
    eggPopClass = function() {
        this.e;
        this.e2;
        this.rewardPage = null; // 奖励预览窗口
        this.rewardData = null; // 金蛋奖励数据
        this.isshow = false;
        this.eggid = 0;
        this.eggprice = null;
        this.avatarDom = null;  // 武将信息窗口
        this.avatarInfoDom = null;
        this.recoverScroller = null; 
        this.isTimeout = [];
    };
    eggPopClass.prototype = {
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
                'c': 'hitegg',
                'm': 'index'
            },
            initRender, true);
            initRender = null;
        },

        initFirst: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('egg');
            that.rewardPage = that.e.find('#eggpop_reward');
            that.avatarDom = that.e.find('#eggpop_info_dom');
            that.avatarInfoDom = that.e.find('#eggpop_info_main');
            
            that.e.find('#eggpop_hidebtn').unbind('touchend').one('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                eggpop.hide();
                e.preventDefault();
            });

            // 奖励预览
            that.e.find('#eggpop_page').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                that.showReward($(this).attr('reward'));
                e.preventDefault();
            });

            // 奖励预览窗口的关闭按钮
            that.e.find('#eggpop_reward_hidebtn').unbind('touchend').bind('touchend', function (e) {
                if (xGame.checkLock(true)) {
                    return true;
                }
                that.recoverScroller.destroy();
                that.recoverScroller = null;
                that.rewardPage.hide();
                e.preventDefault();
            });

            that.rewardPage.find('.mask').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.rewardPage.find('#eggpop_reward_hidebtn').trigger('touchend');
                that.avatarDom.hide();
                e.preventDefault();
            });

            // 奖励预览中的奖励项
            that.rewardPage.undelegate('.box', 'click').delegate('.box', 'click', function (e) {
               
               if (xGame.checkLock(true)) {
                    return true;
                }

                that.rewardPage.find('.box').removeClass('selected');
                $(this).addClass('selected');

                // 如果是武将
                if ($(this).hasClass('type5')) {
                    var index = $('#eggpop_carousel .box').index($(this)); // 取得武将在所有奖励中的索引
                    that.showHeroInfo(that.rewardData[index].general);
                }

                e.preventDefault();
                e.stopPropagation();
            });

            that.e.find('#eggpop_info_hide').unbind('touchend').bind('touchend', function (e) {
               
               if (xGame.checkLock(true)) {
                    return true;
                }

                that.avatarDom.hide();
                e.preventDefault();
            });

            that.eggprice = {1: 0, 2: 0, 3: 0};
            if(res.list && res.list.length > 0) {
                for(var i = 0; i < res.list.length; i++) {
                    var _dom = that.e.find('#egg' + res.list[i].id);
                    _dom.find('.desc').append(res.list[i].description);
                    
                    if (res.list[i].open == 0) {
                        _dom.find('.pop_btn_middle').children('.price').html('x'+res.list[i].price);
                        xGame.updateCd('egg' + res.list[i].id, res.list[i].cd);
                    } else {
                        _dom.find('.pop_btn_middle').addClass('free').find('span').html(lang('eggpop_free'));
                    }
                    that.eggprice[res.list[i].id] = res.list[i].price;
                }
            }

            // 砸蛋按钮
            that.e.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend', function() {

                if(xGame.checkLock()) { //不设置属性
                    return false;
                }

                var eggid = $(this).attr('egg');
                that.eggid = eggid;
                that.doEgg(eggid, $(this));
            });

            that.e.show();
            $('#worldloading').hide();

            xGame.showGuide([1702]);
        },
        
        /* 砸蛋 */
        doEgg: function(eggid, eggbtn) {
            var that = this;

            var inResult = false;
            if(!eggid) {//在结果窗里继续砸
                eggid = that.eggid;
                inResult = true;
            }

            var render = function(res) {

                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        xGame.islock = true;
                        player.updateinfo({'gold': res.all_gold});
                        that.eggAnimate(eggid, res, inResult);

                        if (eggbtn && eggbtn.hasClass('free')) {
                            eggbtn.removeClass('free').children('.price').html('x'+that.eggprice[that.eggid]);
                            xGame.updateCd('egg'+that.eggid, res.cd);
                        }
                        break;
                    case 0:
                        xGame.toast.show(lang('internet_error'));
                        break;
                    case -1:
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    case -2:
                        xGame.toast.show(lang('eggpop_param_error'));
                        break;
                    case -3:
                        xGame.toast.show(lang('eggpop_hitegg_fail'));
                        break;
                    case -4:
                        that.closeResult();
                        xGame.goPay(lang('godminepop_gold_not_enough'));
                        break;
                    case -5:
                        xGame.toast.show(lang('eggpop_getward_fail'));
                        break;
                    case -6:
                        xGame.toast.show(lang('eggpop_hero_exist'));
                        break;
                    case -7:
                        xGame.toast.show(lang('role_illegal'));
                        break;
                    case -8:
                        xGame.toast.show(lang('eggpop_dont_fast'));
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'hitegg',
                'm': 'hit_egg',
                'id': eggid
            },
            render); //加载数据
            render = null;
        },

        closeResult: function() {
            var that = this;
            that.e.find('#eggpop_page').removeClass('nolight');
            if(that.e2) {
                that.e2.remove();
                that.e2 = null;
            }
        },

        openResult: function(res) {
            var that = this;

            if(that.e2 != null) {
                that.updateResult(res);
                xGame.doAnimate(that.e2.find('.eggresult'), 'newhero_scalein', '0.5s');
                return false;
            }

            that.e.find('#eggpop_page').addClass('nolight');

            that.e2 = xGame.loadPopTmpl('eggresult');
            that.e2.find('#eggresult_hidebtn').unbind('touchend').bind('touchend', function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.isTimeout.push(setTimeout(function() {
                    that.closeResult();
                }, 300));
            });
            that.e2.find('#redo_btn').unbind('touchend').bind('touchend', function(e) {
                if(xGame.checkLock()) {
                    return false;
                }
                that.e2.find('#eggresultpop_wrap').attr('class', 'content result0');
                that.e2.find('.resultnumber').children('span').html('');
                that.e2.find('#eggresultpop_wrap').find('#eggresult_number_wrap').remove();
                that.doEgg();
                e.preventDefault();
            });

            that.updateResult(res);

            that.e2.show();
            /*
            xGame.doAnimate(that.e2.find('#eggresultpop_wrap'), 'newhero_scalein', '0.5s');*/
        },

        updateResult: function(res) {
            this.e2.find('#eggresultpop_wrap').attr('class', 'content result' + res.reward.type);
            //var got_word = lang('eggpop_got') + lang('eggpop_got_type'+res.reward.type) + 'x' + res.reward.value;
            //this.e2.find('.resultnumber').children('span').html(got_word);
            this.e2.find('#redo_btn').children('em').html('x'+this.eggprice[this.eggid]);
            //文字
            this.showPicNum(res.reward.value, res.reward.quality);

            this.isTimeout.push(setTimeout(function() {
                xGame.unlock();
            }, 700));
        },

        showPicNum: function(number, type) {
            var numberWrap = $('<div id="eggresult_number_wrap" class="eggresult_numbers numberanim' + type + '"></div>');
            var numberOjb = this.createPicNum('-' + number + '');
            numberWrap.append(numberOjb.e);
            this.e2.find('#eggresultpop_wrap').append(numberWrap);

        },

        createPicNum: function(number) {
            var numAnim = new Num({
                id: helper.createUUID("eggnum"),
                src: ximg(staticUrl + 'img/pop/eggresultpop_number.png'),
                max: number.length,
                gap: 42,
                height: 52,
                x: 0,
                y: 0,
                value: number
            });

            return numAnim;
        },

        eggAnimate: function(eggid, res, inResult) {
            var that = this;
            var movieclip;
            var pos = {
                x: 0,
                y: 0,
                scale: 1
            };
            if(inResult) {
                pos = {
                    x: 56,
                    y: 50,
                    scale: 1
                };
            } else {
                if(eggid == 1) {
                    pos.x = -23;
                    pos.y = 100;
                    pos.scale = 0.80;
                } else if(eggid == 2) {
                    pos.x = 280;
                    pos.y = 84;
                } else if(eggid == 3) {
                    pos.x = 584;
                    pos.y = 65;
                    pos.scale = 1.22;
                }
            }

            if(inResult == false) {
                that.e.find('#egg' + eggid).children('.egg').hide();
            } else {
                that.e2.find('#eggresultpop_wrap').attr('class', 'content');
            }
            
            //创建动画底图
            var mc_step = 40;
            var mc = new movieClipClass({
                "img": 'http://img2.hanjiangsanguo.com/hjimg/pop/eggpop_animate.png',
                "width": 365,
                "height": 210,
                "x": pos.x,
                "y": pos.y,
                "scale": pos.scale,
                "loop": false,
                "endDestory": true,
                "callback": function() {
                    if(res.reward.type == 5) {//获得武将，弹武将窗口
                        miscpop.newhero(res.reward.value, function() {
                            xGame.unlock();
                        }, 2000);
                        that.closeResult();
                        if (typeof CD['chat'] != 'undefined') {
                            CD['chat'].read();//手动请求一次，为了早点广播
                        }
                    } else {
                        that.openResult(res);
                    }
                    
                    that.e.find('#egg' + eggid).children('.egg').fadeIn();
                },
                "frames": [{
                    rect: [0, 0],
                    interval: mc_step
                },
                {
                    rect: [365, 0],
                    interval: mc_step
                },
                {
                    rect: [0, 210],
                    interval: mc_step
                },
                {
                    rect: [365, 210],
                    interval: mc_step
                },
                {
                    rect: [0, 420],
                    interval: mc_step
                },
                {
                    rect: [365, 420],
                    interval: mc_step - 10
                },
                {
                    rect: [0, 630],
                    interval: mc_step - 10
                },
                {
                    rect: [365, 630],
                    interval: mc_step - 10
                },
                {
                    rect: [0, 840],
                    interval: mc_step - 10
                },
                {
                    rect: [365, 840],
                    interval: mc_step - 10
                },
                {
                    rect: [0, 1050],
                    interval: mc_step - 20
                },
                {
                    rect: [365, 1050],
                    interval: mc_step
                }]
            });
            //开始播放
            if(inResult) {
                mc.appendTo(this.e2.find('#eggresultpop_wrap'));
                mc.play();
            } else {
                mc.appendTo(this.e.find('#eggpop_page'));
                mc.play();
            }
        },

        // 获取金蛋奖励信息
        showReward: function (id) {
            var that = this;

            var render = function (res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.rewardData = res.list;
                        that.buildRewardList(res.list);
                        break;
                    case 0:
                        xGame.toast.show(lang('internet_error'));
                        break;
                    case -1:
                        xGame.toast.show(lang('eggpop_getward_close'));
                        break;
                    case -2:
                        xGame.toast.show(lang('parameter_error'));
                        break;
                    case -3:
                        xGame.toast.show(lang('eggpop_getward_fail'));
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'hitegg',
                'm': 'get_hitegg_reward',
                'id': id
            }, render);
            render = null;
        },

        // 金蛋奖励列表
        buildRewardList: function (list) {
            var html = '<li>';
            var n = 0;

            for (var i = 0, len = list.length; i < len; i++) {

                var item = list[i];
                
                // 下一页
                if (n > 0 && n % 12 == 0) {
                    html += '</li><li>';
                }

                html += '<div class="box type'+ item.type +'">';
                if (parseInt(item.type) == 5) {
                    html += '<span class="img" style="background-image:url(' + xGame.avatar(item.general.image) + ')"></span>';
                    html += '<em class="name">' + item.general.name + '</em>';
                } else {
                    html += '<span class="img"></span>';
                    html += '<em class="name">' + item.name + '</em>';
                }

                html += '</div>';
                n++;
            }

            html += '</li>';

            this.e.find('.carousel_content').html(html);
            this.recoverScroller = xGame.carousel('eggpop_carousel', 468);
            this.rewardPage.show();
        },

        showHeroInfo: function(data) {
            var html = '';
            html += '<div class="hero_avatar" style="background: url(' + xGame.avatar(data.image, true) + '); 0 0 no-repeat"></div>';
            html += '<p class="hero_name">' + data.name + '</p>';
            html += '<span class="herotype">' + helper.getItemVocation(data.type) + '</span>';
            html += '<p class="hero_stars" style="width: ' + (18 * parseInt(data.star)) + 'px;"></p>';
            html += '<p class="hero_wuli hero_p">' + lang('springshoppop_wuli', [data.wuli]) + '</p>';
            html += '<p class="hero_zhili hero_p">' + lang('springshoppop_zhili', [data.zhili]) + '</p>';
            html += '<p class=" hero_tili hero_p">' + lang('springshoppop_tili', [data.tili]) + '</p>';
            html += '<p class="hero_talent_title">' + lang('springshoppop_talent') + '</p>';
            html += '<p class="hero_talent hero_infop"><span>' + data.talent + '</span>：' + data.talent_desc + '</p>';
            html += '<p class="hero_skill_title">' + lang('springshoppop_skill') + '</p>';
            html += '<p class="hero_skill hero_infop"><span>' + data.skill + '</span>：' + data.skill_desc + '</p>';
            
            this.avatarInfoDom.html(html);
            this.avatarDom.css('display', 'block');
            html = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#eggpop_reward_hidebtn').unbind('touchend');
                this.e.find('#eggpop_hidebtn').unbind('touchend');
                this.e.find('#eggpop_info_hide').unbind('touchend');
                this.e.find('#eggpop_page').undelegate('.pop_btn_small', 'touchend');
                this.e.undelegate('.pop_btn_middle', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.e2) {
                this.e2.find('#eggresult_hidebtn').unbind('touchend');
                this.e2.find('#redo_btn').unbind('touchend');
                this.e2.remove();
                this.e2 = null; 
            }
            if (this.rewardPage) {
                this.rewardPage.find('.mask').unbind('touchend');
                this.rewardPage.undelegate('.box', 'click');
                this.rewardPage.remove();
                this.rewardPage = null;
            }
        },

        hide: function() {
            this.isshow = false;
            xGame.updateCd('egg1', 0);
            xGame.updateCd('egg2', 0);
            xGame.updateCd('egg3', 0);
            
            if (this.avatarDom) {
                this.avatarDom.remove();
                this.avatarDom = null;
            }
            if (this.avatarInfoDom) {
                this.avatarInfoDom.remove();
                this.avatarInfoDom = null;
            }
            this.rewardData = null;
            xGame.unlock();
            
            if (this.recoverScroller != null) {
                this.recoverScroller.destroy();
                delete this.recoverScroller;
            }
            this.unbindAll();
            this.eggid = 0;
            this.eggprice = null;
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            
        }
    };
})();