/* 神树 */
(function() {
    sacredtreePopClass = function() {
        this.e;
        this.isshow = false;
        this.expDom;
        this.btnsDom;
        this.myScroller;
        this.listDom;
        this.rewardDom;
        this.price;//当前价格
        this.time;
        this.isTimeout = null;
    };
    sacredtreePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.initPage(res);
                        break;
                    case -3:
                        xGame.toast.show(lang('sacredtreepop_get_tree_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }

                if (res.status != 1) {
                    $('#worldloading').hide();
                }
            };
            
            loadContents({
                'c': 'sacredtree',
                'm': 'index',
            },
            initRender, true);
            initRender = null;
        },
        
        //设置页面
        initPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('sacredtree');
            
            that.e.find('.content').find('#sacredtreepop_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                sacredtreepop.hide();
                e.preventDefault();
            });
            
            that.expDom = that.e.find('#sacredtreepop_exp_dom');
            that.btnsDom = that.e.find('#sacredtreepop_btns');
            that.listDom = that.e.find('#sacredtreepop_list');
            that.rewardDom = that.e.find('#sacredtreepop_reward_dom');
            
            that.fillBar(parseInt(res.exp), parseInt(res.levelup_exp), true);
            that.fillCifu(res.tree);
            that.price = res.price;
            that.time = res.time;
            that.showBtn(res.time, res.price);
            that.buildList(res.list);
            
            xGame.updateCd('sacredtree', res.cd);
            
            //已经满级了不需要再浇水了
            if (res.btn_status == 1) {
                that.closeAllBtn();
            }
            
            that.bindAll();
            
            that.e.show();
            $('#worldloading').hide();
            
        },
        
        closeAllBtn: function() {
            this.btnsDom.find('#god_water_btn').attr('class', 'pop_btn_middle_disable btn');
            this.btnsDom.find('#water_btn').attr('class', 'pop_btn_middle_disable btn gold');
            this.btnsDom.find('#water_btn').find('b').html('x' + this.btnsDom.find('#water_btn').attr('gold'));
        },
        
        buildList: function(list) {
            if (list != undefined) {
                var html = '';
                for (var i = list.length - 1;i >= 0;i --) {
                    html += '<li class="tree_li' + list[i].id + '" id="tree_li' + list[i].id + '">';
                    html += '<span class="tree_img tree_' + list[i].id + '"></span>';
                    if (list[i].status == 1) {
                        html += '<span class="done"></span>';
                    }
                    html += '<span class="name_bg"></span>';
                    html += '<span class="name">' + list[i].name + '</span>';
                    html += '<ul class="reward_dom">' + this .getRewardHtml(list[i].reward)+ '</ul>';
                    html += '</li>';
                }
                this.listDom.html(html);
                html = null;
            }
            
            if (this.myScroller == null) {
                this.myScroller = new iScroll('sacredtreepop_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.myScroller.scrollTo(0, -150 * list.length + 490);
            this.myScroller.refresh();
        },
        
        waterFunc: function(type) {
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                
                switch (parseInt(res.status)) {

                    case 1:
                        //console.log(res);
                        //暴击
                        if (res.multiple == 1) {
                            xGame.toast.show(lang('sacredtreepop_water_success', [res.add_exp]));
                        } else {
                            xGame.toast.show('<span class="reward_crit rc' + res.multiple + '">' + lang('sacredtreepop_water_success', [res.add_exp]) + '</span>');
                        }
                        player.updateinfo({
                            "gold": res.all_gold,
                            "silver": res.all_silver
                        });
                        that.fillBar(parseInt(res.now_exp), parseInt(res.levelup_exp));
                        
                        if (that.time != res.time && res.time == 0) {
                            that.btnsDom.find('#water_btn').find('b').html('x' + that.price[1]);
                            that.btnsDom.find('#water_btn').addClass('gold');
                        }
                        that.expAnim(Math.floor(parseInt(res.now_exp) / parseInt(res.levelup_exp) * 434), res.levelup);
                        
                        //没升级，解锁
                        if (res.levelup == 0) {
                            xGame.unlock();
                        }
                        
                        if (res.reward.length > 0) {
                            that.showRewardDom(res.reward);
                        }
                        
                        if (parseInt(res.tree_id) > 1) {//树的名称有变化了，并且不是1
                            that.e.find('#cifu_text').html(res.tree_name + '<b>' + lang('sacredtreepop_all_hero_hp_plus', [res.buff]) + '</b>').attr('class', 'tree' + res.tree_id);
                            that.e.find('#my_tree').html(res.tree_name);
                            that.e.find('#sacredtreepop_big_img').attr('class', 'tree' + res.tree_id);
                        }
                        
                        if (res.btn_status == 1) {
                            that.closeAllBtn();
                        }
                        
                        break;
                    case -5:
                        xGame.toast.show(lang('sacredtreepop_is_full'));
                        break;
                    case -4:
                        xGame.goPay(lang('gold_not_enough'));
                        break;
                    case -3:
                        xGame.toast.show(lang('sacredtreepop_free_use_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                
                if (res.status != 1) {
                    xGame.unlock();
                }
            };
            
            loadContents({
                'c': 'sacredtree',
                'm': 'watering',
                'type': type
            },
            Func);
            Func = null;
        },
        
        expAnim: function(endWidth, times) {
            //console.log('结束长度为：' + endWidth + ' ,升级次数为：' + times);
            xGame.islock = true;
            var that = this;
            var obj = this.expDom.find('#sacredtreepop_bar');
            var startWidth = obj.width();
            if (times == 0) {//如果升级次数为0
                if (endWidth - startWidth < 1) {//如果变化很小，animate函数会不自信，直接css设置
                    obj.width(endWidth + 'px');
                } else {
                    obj.animate({'width': endWidth + 'px'}, 500, 'linear');
                }
            } else {//如果升级次数不为0
                /*防止bug*/
                if (startWidth == 434) {
                    obj.width('0px');
                }
                obj.animate({'width': '434px'}, 500, 'linear', function() {
                    times -= 1;
                    if (times < 0) {
                        return;
                    } else {
                        if (times == 0) { //最后一下
                            if (endWidth < 31 || endWidth == 434) {
                                obj.width(endWidth + 'px');
                            } else {
                                obj.width('0px').animate({'width': endWidth + 'px'}, 500, 'linear');
                            }
                        } else { //中间补全
                            obj.width('0px').animate({'width': 434 + 'px'}, 500, 'linear');
                        }
                        that.isTimeout = setTimeout(arguments.callee, 800);
                    }
                });
            }
        },
        
        showRewardDom: function(list) {
            var that = this;
            var temp;
            var roll = function() {
                if (list.length > 0) {
                    temp = list.shift();
                    that.rewardDom.find('ul').html(that.getRewardHtml(temp));
                    that.rewardDom.find('#sacredtreepop_reward_img').attr('class', 'tree_' + temp.id);
                    that.rewardDom.find('#sacredtreepop_reward_img').find('span').html(temp.name);
                    that.rewardDom.find('.pop_btn_middle').unbind('touchend').one('touchend', function() {
                        that.rewardDom.hide();
                        that.listDom.find('#tree_li' + temp.id).append('<span class="done"></span>');
                        roll();
                    });
                    that.rewardDom.show();
                    xGame.doAnimate(sacredtreepop.rewardDom.find('.bg'), 'newhero_scalein', '0.5s');
                } else {
                    xGame.unlock();
                    return false;
                }
            }
            roll();

        },
        
        /*
        1 元宝
        2 突飞卡
        3 银币
        4 黄宝石
        5 紫宝石
        6 原魂
        7 玉石
        8 免费普通继承
        9 免费高级继承
        10 免费白金继承
        11 免费至尊继承
        12 青龙石-物攻13 朱雀石-策攻
        14 玄武石
        15 白虎石
        16 声望
        */
        getRewardHtml: function(reward) {
            var html = '';
            for (var i = 1;i < 5;i ++) {
                if (reward[i].type != 0) {
                    if (reward[i].type == 8) {
                        html += '<li class="r' + reward[i].type + '">' + lang('ordinary_inherit_cd') + ' x' + reward[i].value + '</li>';
                    } else if (reward[i].type == 9) {
                        html += '<li class="r' + reward[i].type + '">' + lang('free_inherit_strengthen') + ' x' + reward[i].value + '</li>';
                    } else if (reward[i].type == 10) {
                        html += '<li class="r' + reward[i].type + '">' + lang('free_inherit_platinum') + ' x' + reward[i].value + '</li>';
                    } else if (reward[i].type == 11) {
                        html += '<li class="r' + reward[i].type + '">' + lang('inherit_cd') + ' x' + reward[i].value + '</li>';
                    } else if (miscpop.in_array(reward[i].type, [12, 13, 14, 15])) {
                        html += '<li class="r' + reward[i].type + '" style="color:' + helper.getItemColor(reward[i].gem_quality) + '">' + reward[i].name + '(Lv' + reward[i].value + ')*' + reward[i].num + this.getGemImg(parseInt(reward[i].num), reward[i].gem_type, reward[i].gem_quality) + '</li>';
                    } else if (reward[i].type == 17) {
                        html += '<li class="r' + reward[i].type + '" style="color:' + helper.getItemColor(reward[i].itemquality) + '">' + reward[i].itemname + '</li>';
                    } else {
                        html += '<li class="r' + reward[i].type + '">x' + reward[i].value + '</li>';
                    } 
                }
            }
            return html;
        },
        
        getGemImg: function(num, type, quality) {
            var img = '';
            for(var i = 0;i < num;i ++) {
                img += '<span class="gem_img" style="background-image:url(' + xGame.gem(type, quality) + ');"></span>';
            }
            return img;
        },
        
        fillBar: function(exp, levelup_exp, setWidth) {
            if (setWidth) {
                this.expDom.find('#sacredtreepop_bar').width(Math.floor(exp / levelup_exp * 434) + 'px');
            }
            this.expDom.find('#sacredtreepop_exp').html(exp);
            this.expDom.find('#sacredtreepop_levelepexp').html(levelup_exp);
        },
        
        fillCifu: function(tree) {
            if (tree.tree_id == 1) {
                this.e.find('#cifu_text').html(lang('no_word'));
            } else {
                this.e.find('#cifu_text').html(tree.now_tree + '<b>' + lang('sacredtreepop_all_hero_hp_plus', [tree.buff]) + '</b>').attr('class', 'tree' + tree.tree_id);
            }
            this.e.find('#my_tree').html(tree.now_tree);
            this.e.find('#sacredtreepop_big_img').attr('class', 'tree' + tree.tree_id);
        },
        
        showBtn: function(time, price) {
            if (time == 1) {
                this.btnsDom.find('#water_btn').find('b').html(lang('sacredtreepop_free_this_time'));
                this.btnsDom.find('#water_btn').removeClass('gold');
            } else {
                this.btnsDom.find('#water_btn').find('b').html('x' + price[1]);
                this.btnsDom.find('#water_btn').addClass('gold');
            }
            this.btnsDom.find('#god_water_btn').find('b').html('x' + price[2]);
            
            this.btnsDom.find('#water_btn').attr('gold', price[1]);
            this.btnsDom.find('#god_water_btn').attr('gold', price[2]);
        },
        
        bindAll: function(){
            var that = this;

            that.e.undelegate('#sacredtreepop_info_btn', 'touchend').delegate('#sacredtreepop_info_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#sacredtreepop_info_dom').css('display','block');
            });
            
            that.e.undelegate('#sacredtreepop_info_hide', 'touchend').delegate('#sacredtreepop_info_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#sacredtreepop_info_dom').css('display','none');
            });
            
            that.e.undelegate('#sacredtreepop_cifu', 'touchend').delegate('#sacredtreepop_cifu', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#sacredtreepop_cifu_dom').css('display','block');
            });
            
            that.e.undelegate('#sacredtreepop_cifu_hide_btn', 'touchend').delegate('#sacredtreepop_cifu_hide_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#sacredtreepop_cifu_dom').css('display','none');
            });
            
            that.btnsDom.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.waterFunc($(this).attr('btype'));
            });
            
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('#sacredtreepop_hide').unbind('touchend');
                this.e.undelegate('#sacredtreepop_info_btn', 'touchend');
                this.e.undelegate('#sacredtreepop_info_hide', 'touchend');
                this.e.undelegate('#sacredtreepop_cifu', 'touchend');
                this.e.undelegate('#sacredtreepop_cifu_hide_btn', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.btnsDom) {
                this.btnsDom.undelegate('.pop_btn_middle', 'touchend');
                this.btnsDom.remove();
                this.btnsDom = null;
            }
        },
        
        hide: function() {
            xGame.unlock();
            this.isshow = false;
            
            xGame.updateCd('sacredtree', 0);
            
            if (this.myScroller != null) {
                this.myScroller.destroy();
                delete this.myScroller;
            }
            if (this.expDom) {
                this.expDom.remove();
                this.expDom = null;
            }
            if (this.listDom) {
                this.listDom.remove();
                this.listDom = null;
            }
            if (this.rewardDom) {
                this.rewardDom.remove();
                this.rewardDom = null;
            }
            if (this.isTimeout) {
                clearTimeout(this.isTimeout);
                this.isTimeout = null;
            }
            this.price = null;
            this.time = null;

            this.unbindAll();
        }
    };
})();