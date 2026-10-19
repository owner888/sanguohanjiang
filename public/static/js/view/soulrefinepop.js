/* 提炼星魂 */
(function() {
    soulrefinePopClass = function() {
        this.furnaceScroller;
    };
    soulrefinePopClass.prototype = {
        init: function() {
            var that = this;
            this.furnacearray = [];
            this.currfurnace = 1;
            this.nowMode = 1;
            this.ismoving = false;
            this.currpoint = 0;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                switch (res.status) {
                    case 1:
                        that.initFirst(res);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'star',
                'm': 'refining_index'
            },
            initRender);
            initRender = null;
        },

        initFirst: function(res) {

            this.e = xGame.loadPopTmpl('soulrefine');
            
            this.bindAll();

            //初始化提炼炉
            this.initFurnaces(res);

            this.refreshTopInfo(res);

            this.currfurnace = res.lastsite;
            this.refreshRightInfo(this.currfurnace);
            this.furnaceScroller.scrollToPage(parseInt(parseInt(this.currfurnace)-1), 0);

            //刷新按钮显示
            this.refreshBtnStatus(parseInt(parseInt(this.currfurnace)-1));

            this.e.show();
            this.isshow = true;
            $('#worldloading').hide();
        },

        bindAll: function() {
            var that = this;

            //关闭按钮
            this.e.find('#soulrefine_hidebtn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            //提炼星魂按钮
            this.e.find('#soulrefine_btn').unbind('touchend').bind('touchend', function(e){

                if (that.e.find('#soulrefine_btn').hasClass('disabled') || xGame.checkLock()) {
                    return false;
                }
                that.e.find('#soulrefine_btn').removeClass('disabled').addClass('disabled');

                var initRender = function(res) {
                    switch (res.status) {
                        case 1:
                            that.getRewards(res);
                            break;
                        default:
                            that.e.find('#soulrefine_btn').removeClass('disabled');
                            xGame.toast.show(res.msg);
                            $('#worldloading').hide();
                            break;
                    }
                    xGame.unlock();
                };
                loadContents({
                    'c': 'star',
                    'm': 'refining',
                    'id': that.currfurnace,
                    'refine_type': that.nowMode
                },
                initRender);
                initRender = null;

                e.preventDefault();
            });

            //提炼炉scroll滑动事件
            this.e.undelegate('.img', 'touchstart').delegate('.img', 'touchstart', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                if (e.touches.length == 1) {
                    that.ismoving = false;
                    var touch = e.touches[0];
                    that.currpoint = touch.pageX;
                }
                e.preventDefault();
            });

            this.e.undelegate('.img', 'touchmove').delegate('.img', 'touchmove', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                if (e.touches.length == 1) {
                    var touch = e.touches[0];
                    var offset = touch.pageX - that.currpoint;

                    if (offset > 10) {//向右划
                        var currindex = that.furnaceScroller.currPageX - 1;

                        if (currindex < 0 || that.ismoving) {
                            return false;
                        }

                        that.ismoving = true;
                        that.refreshBtnStatus(currindex);
                        that.currfurnace = currindex+1;
                        that.refreshRightInfo(that.currfurnace);
                    } else if (offset < -10) {//向左划
                        var currindex = that.furnaceScroller.currPageX + 1;
                        if (currindex > 2 || that.ismoving) {
                            return false;
                        }
                        that.ismoving = true;
                        that.refreshBtnStatus(currindex);
                        that.currfurnace = currindex+1;
                        that.refreshRightInfo(that.currfurnace);
                    }
                }

                e.preventDefault();
            });
            
            this.e.undelegate('.img', 'touchend').delegate('.img', 'touchend', function (e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.ismoving = false;
                that.currfurnace = that.furnaceScroller.currPageX+1;
                that.refreshBtnStatus(that.currfurnace-1);
                that.refreshRightInfo(that.currfurnace);
                e.preventDefault();
            });

        },

        //刷新主公命格界面相关显示
        refreshLordsoulInfo: function(res) {
            var lordsoulpop = window['lordsoulpop'];

            switch(parseInt(res.gettype)) {
                case 1:
                    lordsoulpop.allstar1 = res.star;
                    break;
                case 2:
                    lordsoulpop.allstar2 = res.star;
                    break;
                case 3:
                    lordsoulpop.allstar3 = res.star;
                    break;
                default:
                    break;
            }

            if (lordsoulpop && lordsoulpop.e) {
                lordsoulpop.e.find('#top_info').find('#lord_num'+res.gettype).html(this.getWan(res.star));

                var index = lordsoulpop.currSelected-10*lordsoulpop.lordtreeScroller.currPageX;
                var info = lordsoulpop.lordsoularray[lordsoulpop.lordtreeScroller.currPageX][index];

                if (info) {
                    lordsoulpop.refreshRightInfo(info, lordsoulpop.currSelected);
                }
                
            }
        },
        
        //提炼星魄-- 获得奖励
        getRewards: function(res) {
            this.refreshTopInfo(res);

            this.refreshLordsoulInfo(res);
            
            player.updateinfo({'gold':parseInt(res.gold)});
            
            this.e.find('.furnace'+res.gettype).find('.res_num').html(this.getWan(res.star));

            var callback = function() {
                switch(parseInt(res.gettype)) {
                    case 1://幽蓝星魄
                        xGame.toast.show(lang('lordsoul_msg', [res.gettype, res.getstar, lang('lordsoul_name1')]));
                        break;
                    case 2://铭黄星魄
                        xGame.toast.show(lang('lordsoul_msg', [res.gettype, res.getstar, lang('lordsoul_name2')]));
                        break;
                    case 3://紫阳星魄
                        xGame.toast.show(lang('lordsoul_msg', [res.gettype, res.getstar, lang('lordsoul_name3')]));
                        break;
                    default:
                        break;
                }
            };
            //播放熔炉特效
            this.doFurnaceAnimation(res.gettype, callback);
        },

        //刷新scroll左右滑动按钮
        refreshBtnStatus: function(index) {
            if (index > 0 && index < this.furnacearray.length-1) {
                this.e.find('#refine_scroller_btn_right').show();
                this.e.find('#refine_scroller_btn_left').show();
            } else if (index <= 0) {
                this.e.find('#refine_scroller_btn_right').show();
                this.e.find('#refine_scroller_btn_left').hide();
            } else {
                this.e.find('#refine_scroller_btn_right').hide();
                this.e.find('#refine_scroller_btn_left').show();
            }
        },

        refreshTopInfo: function(res) {
            this.e.find('#top_info').find('#refine_stone_num').html(this.getWan(res.jade));
            this.e.find('#top_info').find('#refine_lord_num1').html(this.getWan(res.jade1));
            this.e.find('#top_info').find('#refine_lord_num2').html(this.getWan(res.jade2));
            this.e.find('#top_info').find('#refine_lord_num3').html(this.getWan(res.jade3));
        },

        getWan: function (num) {

            var num = parseInt(num);

            if (num >= 100000) {
                return Math.floor(parseInt(num) / 10000) + lang('10k');
            } else {
                return num;
            }
        },

        refreshRightInfo: function(id) {
            var that = this;

            var furnace = this.furnacearray[id-1];

            //提炼炉名称
            this.e.find('#refine_right_info').find('#furnacename').html(furnace.name);
            //描述
            this.e.find('#refine_right_info').find('#furnacedesc').html(furnace.desc);
            //提炼方式
            var html = '';
            var n = 0;

            for (var i = 1; i <= 3; i++) {
                var typedata = furnace['refine_type'+i];
                html += '<li uid="' + i + '"' + (n == 0 ? ' class="selected"': '') + '><em></em>' + lang('soulrefine_type'+i);

                if (typedata.value1 > 0) {
                    html += '<span class="refine_type'+typedata.type1+'">'+this.getWan(typedata.value1)+'</span>';
                }

                if (typedata.value2 > 0) {
                    html += '<span class="refine_type'+typedata.type2+'">'+this.getWan(typedata.value2)+'</span>';
                }

                if (typedata.value3 > 0) {
                    html += '<span class="refine_type'+typedata.type3+'">'+this.getWan(typedata.value3)+'</span>';
                }
                html += '</li>';

                if (n == 0) {//默认第一种提炼方式
                    this.nowMode = i;
                }

                n++;
            }

            this.e.find('#refinetype_list').html(html);
            this.getSoulNum();
            
            that.e.find('#refinetype_list').children('li').unbind('touchend').bind('touchend', function() {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#refinetype_list').children('li').each(function() {
                    $(this).removeClass('selected');
                });

                that.nowMode = $(this).attr('uid');
                $(this).addClass('selected');
                that.getSoulNum();
            });
        },

        // 获得星魄的数量
        getSoulNum: function () {
            this.e.find('.get_lord_num').html(lang('eggpop_got') + this.furnacearray[this.currfurnace - 1]['refine_type' + this.nowMode].getvalue + lang('lordsoul_name' + this.currfurnace));
        },

        initFurnaces: function(list) {
            var that = this;

            for (var i in list.refine) {
                this.furnacearray.push(list.refine[i]);
            }
            var html = this.getHtml(list);
            this.e.find('#soulrefine_list').html(html).width(this.furnacearray.length * 254 + 'px');
            html = null;

            if (this.furnaceScroller == null) {
                this.furnaceScroller = new iScroll('soulrefine_scroller', {
                    snap: 'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }

            this.furnaceScroller.refresh();

            //绑定滑动门左右按钮
            this.e.find('#refine_scroller_btn_left').unbind('touchend').bind('touchend', function() {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.furnaceScroller.scrollToPage('prev', 0);
                that.currfurnace = that.furnaceScroller.currPageX+1;
                that.refreshRightInfo(that.furnaceScroller.currPageX+1);
                that.refreshBtnStatus(that.furnaceScroller.currPageX);

                return false;
            });

            this.e.find('#refine_scroller_btn_right').unbind('touchend').bind('touchend', function() {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.furnaceScroller.scrollToPage('next', 0);
                that.currfurnace = that.furnaceScroller.currPageX+1;
                that.refreshRightInfo(that.furnaceScroller.currPageX+1);
                that.refreshBtnStatus(that.furnaceScroller.currPageX);

                return false;
            });
        },

        getHtml: function (list) {
            var html = '';
           
            for (var i = 0, len = this.furnacearray.length; i < len; i++) {
                var pageitem = this.furnacearray[i];
                html += '<li>';
                html += '<div class="img furnace'+pageitem.id+'" uid="'+pageitem.id+'">';
                html += '<span class="res own_res'+(i+1)+'"><em class="res_txt">'+lang('soulrefine_own')+'</em><span class="res'+pageitem.id+'"></span><em class="res_num">'+this.getWan(list['star'+(i+1)])+'</em></span>';
                html += '</div></li>';
            }

            return html;
        },

        doFurnaceAnimation: function(index, callback) {
            var that = this;
            var mc = new movieClipClass({
                "img": 'http://img2.hanjiangsanguo.com/hjimg/pop/furnace_animation'+index+'.png',
                "width": 350,
                "height": 350,
                "x": -47,
                "y": -2,
                "scale": 1,
                "loop": false,
                "endDestory": true,
                "callback": function() {

                    if (that.e) {
                        that.e.find('#soulrefine_btn').removeClass('disabled');
                    }

                    if (callback) {
                        callback();
                    }
                },
                "frames": [{
                    rect: [0, 0],
                    interval: 100
                },
                {
                    rect: [350, 0],
                    interval: 100
                },
                {
                    rect: [700, 0],
                    interval: 100
                },
                {
                    rect: [1050, 0],
                    interval: 100
                },
                {
                    rect: [1400, 0],
                    interval: 100
                },
                {
                    rect: [0, 350],
                    interval: 100
                },
                {
                    rect: [350, 350],
                    interval: 100
                },
                {
                    rect: [700, 350],
                    interval: 100
                },
                {
                    rect: [1050, 350],
                    interval: 100
                },
                {
                    rect: [1400, 350],
                    interval: 100
                },
                {
                    rect: [0, 700],
                    interval: 100
                },
                {
                    rect: [350, 700],
                    interval: 100
                },
                {
                    rect: [700, 700],
                    interval: 100
                },
                {
                    rect: [1050, 700],
                    interval: 100
                },
                {
                    rect: [1400, 700],
                    interval: 100
                },
                {
                    rect: [0, 1050],
                    interval: 100
                },
                {
                    rect: [350, 1050],
                    interval: 100
                },
                {
                    rect: [700, 1050],
                    interval: 100
                },
                {
                    rect: [1050, 1050],
                    interval: 100
                },
                {
                    rect: [1400, 1050],
                    interval: 100
                }]
            });

            mc.appendTo(this.e.find('.furnace'+index));
            mc.play();
            mc = null;
        },
        
        unbindAll: function() {
            this.e.find('#soulrefine_hidebtn').unbind('touchend');
            this.e.find('#soulrefine_scroller').undelegate('.img', 'click');
            this.e.find('#soulrefine_btn').unbind('touchend');
            this.e.find('#refinetype_list').children('li').unbind('touchend');
            this.e.find('#refine_scroller_btn_left').unbind('touchend');
            this.e.find('#refine_scroller_btn_right').unbind('touchend');
        },

        hide: function() {
            this.isshow = false;
            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }
            if (this.furnaceScroller) {
                this.furnaceScroller.destroy();
                this.furnaceScroller = null;
            }
            this.currfurnace = 1;
            this.nowMode = 1;
            this.ismoving = false;
            this.currpoint = 0;
        }
    };
})();