(function() {
    strongachievePopClass = function() {
        this.e;
        this.isshow = false;
        this.itemData;
        this.myScroll;
        this.myScroll2;
        this.scrollDom1;
        this.scrollDom2;
        this.itemId; //当前的装备自增ID
        this.rewardTimer = null;
    };

    strongachievePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {

                if (res.status == -2) {
                    xGame.toast.show(lang('strongachievepop_refine_error'));
                    $('#worldloading').hide();
                    return;
                } else if(res.status == -1) {
                    xGame.toast.show(lang('strongachievepop_achieve_error'));
                    $('#worldloading').hide();
                    return;
                } else if(res.status == 0) {
                    xGame.toast.show(lang('refineachievepop_requst_fail'));
                    $('#worldloading').hide();
                    return;
                } else if(res.status == 1) {
                    /* 保存数据 */
                    that.data = res;

                    /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
                    that.e = xGame.loadPopTmpl('strongachieve');
                    that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                    function(e) {
                        if (xGame.checkLock(true)) {
                            return false;
                        }
                        that.hide();

                        if ($('#activitypop').length == 0) {
                            xGame.showpop('strong');
                        }
                        e.preventDefault();
                    });
                    that.scrollDom1 = that.e.find('#strongitempop_scroller');
                    that.scrollDom2 = that.e.find('#strongachievepop_scroller');

                    /* 第二步 构建数据列表等 */
                    that.buildItemList();

                    /* 展示弹窗 */
                    that.e.show();
                    $('#worldloading').hide();

                    xGame.showpop('system', {'pop': 'strongachieve'});
                } else {                    
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                }
            };
            loadContents({
                'c': 'strengthen',
                'm': 'achievement'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        /* 构建装备列表 */
        buildItemList: function() {
            var that = this;

            var html = '';
            var n = 0;
            for (var i in that.data.itemList) {

                var item = that.data.itemList[i];
                html += '<li id="strongitem' + item.id + '" class="listitem c_i_bj' + item.quality + (n == 0 ? ' selected' : '') + '" itemid="' + item.id + '">' + (item.level != 0 ? '<span>' + item.level + '</span>': '') + '<div class="itempic" style="background-image:url(' + xGame.itemurl(item.equipid) + ');' + '"></div>' + (item.generalname != undefined ? '<p class="name">' + item.generalname + '</p>': '') + '</li>';
                n++;
            }
            that.itemId = that.data.itemList[0].id;
            that.scrollDom1.find('.scroller_content').html(html);

            /* 创建滑动门 domid, 单页宽度 */
            that.myScroll = xGame.scroller('strongitempop_scroller', n * 118);

            /* 显示解锁成就数据*/
            that.showItemAchieve(that.data.achieveList, that.data.itemInfo);
            
            that.bindAll();
        },

        /* 构建单个装备的成就列表 */
        showItemAchieve: function(data, itemInfo) {
            var that = this;
            var html1 = '';
            var strongtype;

            for(var i in data) {
                html1 += '<li class="strongachieve_list_li quality'+ data[i].item.quality +'" id="achieve' + data[i].id + '" quality="'+ data[i].item.quality +'">';
                html1 += '<span class="strongachieve_lv">' + lang('strongachievepop_level', [data[i].level]) + '</span>';
                var langname = 'strongachievepop_unlock_attribute' + itemInfo.type;
                html1 += '<div class="strongachieve_text strongachieve_text1">' + lang(langname, [data[i].add_per]) + '</div>';

                html1 += '<div class="strongachieve_text strongachieve_text2">' + lang('strongachievepop_unlock_need') + '<em class="item_essence" style="color:' + helper.getItemColor(data[i].item.quality) + ';background-image: url(' + xGame.essence(data[i].item.id) + ');">' + data[i].item.name + 'x' + data[i].item.num + '</em><em class="have_num">' + lang('strongachievepop_have_num', [data[i].item.all_num]) + '</em>';
                if(data[i].lock == 0) {
                    if(parseInt(data[i].level) <= parseInt(itemInfo.level)) {
                        html1 += '<botton class="btn pop_btn_small achieve_unlock" aid="' + data[i].id + '">' + lang('unlock') + '</botton>';
                    } else {
                        html1 += '<botton class="btn pop_btn_small_disable achieve_unlock" aid="' + data[i].id + '">' + lang('unlock') + '</botton>';
                    }
                } else {
                    html1 += '<botton class="achieve_unlocked" aid="' + data[i].id + '"></botton>';
                }
                html1 += '</div>';
                html1 += '</li>';
            }

            that.scrollDom2.find('#strongachieve_list_scroller').html(html1);

            that.myScroll2 = new iScroll('strongachievepop_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            that.myScroll2.refresh();
            $('#ajaxloading').hide();
        },

        bindAll: function() {
            var that = this;

            that.scrollDom1.undelegate('li', 'touchstart').delegate('li', 'touchstart',
             function() {
                 that.scrollDom1.find('li').removeClass('selected');
                 $(this).addClass('selected');
            });

            //装备框选择
            that.scrollDom1.undelegate('li', 'touchend').delegate('li', 'touchend',
            function() {
                var id = $(this).attr('itemid');

                if (id != that.itemId) {
                    that.itemRequest(id);
                }
                that.itemId = id;
            });

            //成就解锁按钮
            that.scrollDom2.undelegate('.achieve_unlock', 'touchend').delegate('.achieve_unlock.pop_btn_small', 'touchend',
            function() {
                var id = $(this).attr('aid');
                that.unlockRequest(id);
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

             /*玩法说明*/
            that.e.undelegate('#strongachieve_info', 'touchend').delegate('#strongachieve_info', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#strongachievepop_small').css('display', 'block');
            });
            that.e.find('#strongachievepop_small_hide').unbind('touchend').bind('touchend', function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#strongachievepop_small').css('display', 'none');
            });

            // 重置按钮
            that.e.find('#strongachieve_reset').bind('touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.resetAchieveInfo();
                e.preventDefault();
            });

        },

        unlockRequest: function(id) {
        	var that = this;

        	var initRender = function(res) {

                if (res.status == -2) {
                    xGame.toast.show(lang('strongachievepop_refine_error'));
                	$('#ajaxloading').hide();
                    return;
                } else if(res.status == -1) {
                    xGame.toast.show(lang('strongachievepop_achieve_error'));
                	$('#ajaxloading').hide();
                    return;
                } else if(res.status == 0) {
                    xGame.toast.show(lang('refineachievepop_requst_fail'));
                	$('#ajaxloading').hide();
                    return;
                } else if(res.status == 1) {                	
                	$('#ajaxloading').hide();
                    //播动画
                	that.showAnim();

                    //改变解锁按钮
                    that.scrollDom2.find('#achieve' + id).find('.achieve_unlock').attr('class', 'achieve_unlocked').html('');

                    var quality = that.scrollDom2.find('#achieve' + id).attr('quality');

                    that.scrollDom2.find('.quality' + quality).find('.have_num').html(lang('strongachievepop_have_num', [res.m_num]));
                    return;
                } else if(res.status == 2) {
                    xGame.toast.show(lang('parameter_error'));
                	$('#ajaxloading').hide();
                    return;
                } else if(res.status == 3) {
                    xGame.toast.show(lang('equip_not_exist'));
                	$('#ajaxloading').hide();
                    return;
                } else if(res.status == 4) {
                    xGame.toast.show(lang('unlocked'));
                	$('#ajaxloading').hide();
                    return;
                } else if(res.status == 5) {
                    xGame.toast.show(lang('strongachievepop_refine_lack', [res.essInfo.name, helper.getItemColor(res.essInfo.quality)]));
                    $('#ajaxloading').hide();
                    return;
                } else if(res.status == 6) {
                    xGame.toast.show(lang('strongachievepop_order_unlock'));
                    $('#ajaxloading').hide();
                    return;
                } else {
                    xGame.toast.show(lang('undefined_error'));
                	$('#ajaxloading').hide();
                }
            };
            loadContents({
                'c': 'strengthen',
                'm': 'achieve_unlock',
                'id': that.itemId,
                'aid': id
            },
            initRender); //加载数据
            initRender = null;
        },

        /* 强化结果 */
        showAnim: function() {

            var html = $('<div class="strongachieve_result_warp"></div>');

            var movieclip;

            var cfg = {
                "x": 176,
                "y": -50
            };
            var callback = function() {
                setTimeout(function() {
                    html.fadeOut();
                },
                1000);
            }

            //创建动画底图
            movieclip = new movieClipClass({
                "img": staticUrl + 'img/pop/strongpop_result_anim.png',
                "width": 300,
                "height": 220,
                "x": cfg.x,
                "y": cfg.y,
                "loop": false,
                "callback": callback,
                "endDestory": true,
                "frames": [{
                    rect: [0, 0],
                    interval: 90
                },
                {
                    rect: [300, 0],
                    interval: 90
                },
                {
                    rect: [600, 0],
                    interval: 90
                },
                {
                    rect: [0, 220],
                    interval: 90
                },
                {
                    rect: [300, 220],
                    interval: 90
                },
                {
                    rect: [600, 220],
                    interval: 90
                },
                ]
            });
            movieclip.appendTo(html);
            //创建title
            html.append('<span class="unlock_ok"></span>');
            //开始
            this.e.find('#strongachievepop_wrap').append(html);
            movieclip.play();
            movieclip = null;
        },


        itemRequest: function(id) {
            var that = this;

            var initRender = function(res) {

                if (res.status == -2) {
                    xGame.toast.show(lang('strongachievepop_refine_error'));
                	$('#ajaxloading').hide();
                    return;
                } else if(res.status == -1) {
                    xGame.toast.show(lang('strongachievepop_achieve_error'));
                	$('#ajaxloading').hide();
                    return;
                } else if(res.status == 0) {
                    xGame.toast.show(lang('refineachievepop_requst_fail'));
                	$('#ajaxloading').hide();
                    return;
                } else if(res.status == 1) {
                    that.showItemAchieve(res.achieveList, res.itemInfo);
                } else {
                    xGame.toast.show(lang('undefined_error'));
                	$('#ajaxloading').hide();
                }
            };
            loadContents({
                'c': 'strengthen',
                'm': 'achievement',
                'id': id
            },
            initRender); //加载数据
            initRender = null;
        },

        // 重置成就信息
        resetAchieveInfo: function () {
            
            var that = this;

            var render = function (res) {
                
                xGame.unlock();
                
                if (parseInt(res.status) == 1) {
                    that.showItemAchieve(res.achieveList, res.itemInfo);
                    that.showEssenceNum(res.jinghua);
                } else {
                    xGame.toast.show(res.msg);
                }
            };

            loadContents({
                'c': 'strengthen',
                'm': 'revert_achieve',
                'id': that.itemId,
            }, render);
        },

        // 显示返回的精华
        showEssenceNum: function (data) {
            
            var that = this;
            
            var rewardBox = that.e.find('.reward_box');

            if (rewardBox.length > 0) {
                rewardBox.remove();
            }

            var html = '';
            var item = null;

            html += '<div class="reward_box">';

            for (var i = 0, len = data.length; i < len; i++) {
                item = data[i];
                html += '<span class="reward_down pos'+ (i + 1) +'" style="background-image: url('+ xGame.essence(item.id) +');color: '+ helper.getItemColor(item.quality) +';">x' + item.value + '</span>';
            }

            html += '</div>';
            that.e.append(html);
            
            that.rewardTimer = setTimeout(function() {
                
                if (that.e.css('display') == 'block') {
                    that.e.find('.reward_box').remove();
                }

            }, 3000);
            
            html = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('.scroller_btn_left').unbind('touchend');
                this.e.find('.scroller_btn_right').unbind('touchend');
                this.e.find('#strongachievepop_small_hide').unbind('touchend');
                this.e.undelegate('#strongachieve_info', 'touchend');
                this.e.remove();
                this.e = null;
            } 
            if (this.scrollDom1) {
                this.scrollDom1.undelegate('li', 'touchstart');
                this.scrollDom1.undelegate('li', 'touchend');
                this.scrollDom1.remove();
                this.scrollDom1 = null;
            }
            if (this.scrollDom2) {
                this.scrollDom2.undelegate('.achieve_unlock', 'touchend');
                this.scrollDom2.remove();
                this.scrollDom2 = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.itemData = null;
            if (this.myScroll) {
                this.myScroll.destroy();
                delete this.myScroll;
            }
            if (this.myScroll2) {
                this.myScroll2.destroy();
                delete this.myScroll2;
            }

            if (this.rewardTimer) {
                clearTimeout(this.rewardTimer);
            }

            this.rewardTimer = null;
            this.itemId = null;
            this.unbindAll();
        },
    };
})();