/* 背包 */
(function() {
    vipPopClass = function() {
        this.e;
        this.isshow = false;

        this.topDom;
        this.bottomDom;

        this.currentVipLevel; //当前vip等级
        this.heroData;//以image为键
    };
    vipPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            if (player.info.appreview == 1) { //苹果审核期间，直接去充值
                xGame.enterPay();
                return false;
            }

            var initRender = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('no_vip_info'));
                    $('#worldloading').hide();
                    break;
                case 1:
                    that.buildPage(res);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                    break;
                }
            };

            loadContents({
                'c':
                'vip',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        buildPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('vip');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                vippop.hide();
                e.preventDefault();
            });

            that.e.find('#vip_pay_btn').unbind('touchend').bind('touchend',
            function(e) {
                xGame.enterPay();
                vippop.hide();
                e.preventDefault();
            });

            that.topDom = that.e.find('#vip_top');
            that.bottomDom = that.e.find('#vip_bottom');
            that.heroData = {};
            that.topDom.find('#vip_exp').html(res.exp + '/' + res.top_exp);
            that.topDom.find('#vip_exp_bar').animate({
                'width': (res.exp / res.top_exp * 475 + 30) + 'px'
            },
            500);

            if (parseInt(res.next_level) != -1) {
                that.topDom.find('#vip_info').html('<b class="gr">' + lang('current_lv') + '</b><b id="current_vip_lv">VIP' + res.level + '</b><b class="bl">' + lang('pay_more_vip', [res.diff, res.next_level]) + '</b>');
            } else {
                that.topDom.find('#vip_info').html('<b class="gr">' + lang('current_lv') + '</b><b id="current_vip_lv">VIP' + res.level + '</b><b class="bl">' + lang('you_reach_top_vip') + '</b>');
            }

            var html = '',
            button = '',
            rightInfo = '';
            
            var unreceive = 0;
            res.list = res.list || {};
            /*暂时先写死*/
            for (var i = 1; i <= 100; i++) {
                if (res.list[i] != undefined) {
                    rightInfo = '';
                    var rights = res.list[i].right || [];
                    for (var j = 0; j < rights.length; j++) {
                        //console.log(res.list[i].right[j]);
                        if (res.list[i].status == 0) {
                            button = '<button id="vip_lvbtn' + i + '" class="btn pop_btn_big_disable" level="' + i + '">' + lang('not_open') + '</button>';
                        } else if (res.list[i].status == 1) {
                            unreceive = i;
                            button = '<button id="vip_lvbtn' + i + '" class="btn pop_btn_big"  level="' + i + '">' + lang('open') + '</button>';
                        } else if (res.list[i].status == 2) {
                            button = '<button id="vip_lvbtn' + i + '" class="btn pop_btn_big_disable"  level="' + i + '">' + lang('had_open') + '</button>';
                        }
                        if (res.list[i].right[j].gettype == 1) {
                            rightInfo += '<p class="wuzi silver">' + res.list[i].right[j].getvalue + '</p>';
                        } else if (res.list[i].right[j].gettype == 2) {
                            if (res.list[i].right[j].generalname != '') {//80%
                                rightInfo += '<p style="font-size:20px">' + lang('you_had_get_this_hero', [res.list[i].right[j].generalname]) + '</p><p style="font-size:20px">' + lang('reward_change_to') + '</p>';
                                rightInfo += '<p class="wuzi gem gem' + res.list[i].right[j].jadetype + '">' + res.list[i].right[j].jadenum + '&nbsp;&nbsp;<small>' + lang('back_80_percent') + '</small></p>';
                            } else {
                                rightInfo += '<p class="wuzi gem gem' + res.list[i].right[j].jadetype + '">' + res.list[i].right[j].jadenum + '</p>';
                            }

                        } else if (res.list[i].right[j].gettype == 3) {
                            if (res.list[i].right.length == 1) {//单件装备
                                rightInfo += '<div class="itembox c_i_bj' + res.list[i].right[j].quality + '"><span style="background-image: url(' + xGame.itemurl(res.list[i].right[j].id) + '); background-position: 50% 50%; background-repeat: no-repeat no-repeat; "></span></div>';
                                rightInfo += '<div class="iteminfo_p"><h4 style="color: ' + helper.getItemColor(res.list[i].right[j].quality) + '">' + res.list[i].right[j].name + '</h4><p class="needlevel">' + lang('need_lv') + '<span>' + res.list[i].right[j].needlevel + '</span></p><p class="powerup">' + helper.getItemPowerName(res.list[i].right[j].type) + '：<span>' + res.list[i].right[j].power + '</span></p></div>';
                            } else {//多件装备,写死青龙偃月刀套装
                                if (j == 0) {
                                    rightInfo += '<div class="item_set1"></div>';
                                    rightInfo += '<div class="iteminfo_p sp"><p><em style="color: ' + helper.getItemColor(res.list[i].right[j].quality) + '">' + res.list[i].right[j].name + '</em>' + lang('lv_can_dress', [res.list[i].right[j].needlevel]) + '</p>';
                                } else if (j == res.list[i].right.length - 1) {
                                    rightInfo += '<p><em style="color: ' + helper.getItemColor(res.list[i].right[j].quality) + '">' + res.list[i].right[j].name + '</em>' + lang('lv_can_dress', [res.list[i].right[j].needlevel]) + '</p></div>';
                                } else {
                                    rightInfo += '<p><em style="color: ' + helper.getItemColor(res.list[i].right[j].quality) + '">' + res.list[i].right[j].name + '</em>' + lang('lv_can_dress', [res.list[i].right[j].needlevel]) + '</p>';
                                }
                            }
                        } else if (res.list[i].right[j].gettype == 4) {
                            rightInfo += '<div class="avatarbox" image="' + res.list[i].right[j].image + '"><span class="avatar" style="background-image: url(' + xGame.avatar(res.list[i].right[j].image) + '); left:16px; background-position: 50% 50%; background-repeat: no-repeat no-repeat; "></span></div>';
                            if(appLanguage == 'th') {
                                rightInfo += '<div class="heroinfo_p"><p class="name">' + res.list[i].right[j].name + '</p></div>';
                            } else {
                                rightInfo += '<div class="heroinfo_p"><p class="name p2"><span class="name">' + res.list[i].right[j].name + '</span></p><p class="wuli p1">' + lang('wuli') + '：<span>' + res.list[i].right[j].wuli + '</span></p><p class="tf p2">' + lang('tianfu') + '：<span>' + res.list[i].right[j].talent.name + '</span></p><p class="zhili p1">' + lang('zhili') + '：<span>' + res.list[i].right[j].zhili + '</span></p><p class="jn p2">' + lang('skill') + '：<span>' + res.list[i].right[j].skill.name + '</span></p><p class="tili p1">' + lang('tili') + '：<span>' + res.list[i].right[j].tili + '</span></p></div>';
                            }
                            rightInfo += '<p class="muster_star">' + lang('star_rank', [(parseInt(res.list[i].right[j].star) * 18)]) + '</p>';
                            that.heroData[res.list[i].right[j].image] = res.list[i].right[j];
                        } else if (res.list[i].right[j].gettype == 5) {
                            rightInfo += '<p class="wuzi jade">' + res.list[i].right[j].getvalue + '</p>';
                        } else if (res.list[i].right[j].gettype == 6) {
                            rightInfo += '<div class="itembox c_i_bj' + res.list[i].right[j].soul_quality + '"><span style="background-image: url(static/img/pop/soul_' + res.list[i].right[j].soul_type + res.list[i].right[j].soul_quality + '.png' + '); background-position: 50% 50%; background-repeat: no-repeat no-repeat; "></span></div>';
                            rightInfo += '<p class="wuzi text soul_name" style="color: ' + helper.getItemColor(res.list[i].right[j].soul_quality) + '">' + res.list[i].right[j].soul_name + '  Lv.' + res.list[i].right[j].soul_level + '</p>';
                            rightInfo += '<p class="wuzi text">' + lang('soul_properties') + ':' + helper.getSoulTypeName(parseInt(res.list[i].right[j].soul_type)) + '+' + res.list[i].right[j].soul_addition + '</p>';
                        } else if (res.list[i].right[j].gettype == 7) {
                            rightInfo += '<p class="wuzi tfcard">' + res.list[i].right[j].getvalue + '</p>';
                        } else if (res.list[i].right[j].gettype == 8) {
                            rightInfo += '<p class="wuzi reputation">' + res.list[i].right[j].getvalue + '</p>';
                        }
                    }
                    html += '<li>';
                    html += '<div class="vip_bottom_l">';
                    html += '<p class="vip_left_title"><span>VIP' + i + '</span>' + lang('function_desc') + '</p>';
                    html += '<p class="info">' + res.list[i].left + '</p>';
                    html += '</div>';
                    html += '<div class="vip_bottom_r' + ((miscpop.in_array(res.list[i].right[0].gettype, [4]) || (res.list[i].right[0].gettype == 3 && res.list[i].right.length != 1))? ' no': '') + '">';
                    html += '<p class="vip_left_title"><span>VIP' + i + '</span>' + lang('huikui_gift') + '</p>';
                    html += '<div class="info">' + rightInfo + '</div>' + button + '</div>'
                    html += '</li>';
                }
            }
            that.bottomDom.find('#vip_list').html(html);

            that.myScroll = xGame.carousel('vip_carousel', 901);

            that.bottomDom.find('#vip_list').undelegate('button.pop_btn_big', 'touchend').delegate('button.pop_btn_big', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.openGift(parseInt($(this).attr('level')));
            });

            that.currentVipLevel = parseInt(res.level);
            
            if (that.currentVipLevel > 0) {
            if (unreceive != 0) {//说明有可以领取的礼包，去到可以领取的礼包那
                that.myScroll.scrollTo((unreceive - 1) * -901, 0);
                that.myScroll.currPageX = unreceive - 1;
            } else {
                that.myScroll.scrollTo((that.currentVipLevel - 1) * -901, 0);
                that.myScroll.currPageX = that.currentVipLevel - 1;
                }
                
                that.bottomDom.find('.carousel_page').find('.active').removeClass('active');
                that.bottomDom.find('.carousel_page').find('li').eq(that.myScroll.currPageX).addClass('active');
            }
            unreceive = null;
            
            that.e.show();
            $('#worldloading').hide();

            /*
            if(player.info.vip_reward == 0) {
                xGame.showpop('firstpay');
            }
            */
            that.bottomDom.undelegate('.avatarbox', 'click').delegate('.avatarbox', 'click',
            function() {
                if (xGame.checkLock(true) || $(this).attr('image') == undefined) { //不设置属性
                    return false;
                }
                var data = that.heroData[$(this).attr('image')];
                miscpop.showHeroInfo(data);
                data = null;
            });
        },

        openGift: function(level) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('have_no_unreceive_gift'));
                    break;
                case - 2 : xGame.toast.show(lang('gift_not_receive'));
                    break;
                case - 3 : xGame.toast.show(lang('gift_had_receive'));
                    break;
                case - 4 : 
                    //提示已经领取过，跳到下一个
                    xGame.toast.show(lang('had_receive'));
                    that.bottomDom.find('#vip_list').find('#vip_lvbtn' + level).html(lang('had_open')).removeClass('pop_btn_big').addClass('pop_btn_big_disable');
                    if (that.currentVipLevel - 1 >= level) {
                        that.bottomDom.find('#vip_list').find('#vip_lvbtn' + (level + 1)).html(lang('open')).addClass('pop_btn_big').removeClass('pop_btn_big_disable');
                        setTimeout(function() {
                            that.myScroll.scrollToPage('next', 0);
                        },
                        1000);
                    }
                    break;
                case 1:
                    var str = '';
                    
                    if (res.equip != undefined) {
                        if (res.equip.length == 1) {//一个装备的时候
                            xGame.toast.show(lang('you_get_item', [helper.getItemTypeName(parseInt(res.equip[0].itemtype)), helper.getItemColor(parseInt(res.equip[0].itemquality)), res.equip[0].itemname]));
                        } else {//多个装备的时候
                            xGame.toast.show(lang('you_get_item_set'));
                        }
                    } else if (res.general != undefined) {
                        miscpop.newhero(res.general);
                        if (typeof CD['chat'] != 'undefined') {
                            CD['chat'].read();//手动请求一次，为了早点广播
                        }
                    } else {
                        if (res.jadetype != undefined) {
                            str += '<em class="reward gem' + res.jadetype + '">' + res.jadenum + '</em> ';
                        }
                        
                        if (res.silver != undefined) {
                            player.updateinfo({
                                'silver': parseInt(res.all_silver)
                            });
                            str += '<em class="reward silver">' + res.silver + '</em> ';
                        }
                        
                        if (res.freeleap != undefined) {
                            str += '<em class="reward tfcard">' + res.freeleap + '</em> ';
                        }
                    
                        if (res.soul != undefined) {//将魂
                            str += '<em style="color: ' + helper.getItemColor(res.soul_quality) + '">' + res.soul_name + '</em> ';
                        }
                    
                        if (res.stone != undefined) {
                            str += '<em class="reward jade">' + res.stone + '</em> ';
                        }
                        
                        if (res.reputation != undefined) {
                            str += '<em class="reward prestige">' + res.reputation + '</em>';
                        }
                        
                        if (appLanguage == 'kr') {//韩文做语序调整
                            xGame.toast.show(lang('you_get', [str]));
                        } else {
                            xGame.toast.show(lang('you_get') + str);
                        }
                    }
                    
                    //自动到下一个
                    that.bottomDom.find('#vip_list').find('#vip_lvbtn' + level).html(lang('had_open')).removeClass('pop_btn_big').addClass('pop_btn_big_disable');
                    if (that.currentVipLevel - 1 >= level) {
                        that.bottomDom.find('#vip_list').find('#vip_lvbtn' + (level + 1)).html(lang('open')).addClass('pop_btn_big').removeClass('pop_btn_big_disable');
                        setTimeout(function() {
                            that.myScroll.scrollToPage('next', 0);
                        },
                        1000);
                    }
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'vip',
                'm': 'get_gift',
                'l': level
            },
            Func); //加载数据
            Func = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('#vip_pay_btn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.bottomDom) {
                this.bottomDom.find('#vip_list').undelegate('button.pop_btn_big', 'touchend');
                this.bottomDom.undelegate('.avatarbox', 'click');
                this.bottomDom.remove();
                this.bottomDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.currentVipLevel = this.heroData = null;
            this.unbindAll();
            if (this.topDom) {
                this.topDom.remove();
                this.topDom = null;
            }
            if (this.myScroll) {
                this.myScroll.destroy();
                this.myScroll = null;
            }
        }
    };
})();