/* 主公星魄 */
(function() {
    lordsoulPopClass = function() {
        this.lordtreeScroller;
        this.currSelected = 1;
        this.currpoint = 0;
    };
    lordsoulPopClass.prototype = {
        init: function() {
            this.lordsoularray = [];
            this.isanimating = false;
            this.allstar1 = this.allstar2 = this.allstar3 = 0;
            this.islevelup = false;
            this.ismoving = false;
            var that = this;

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
                'm': 'index'
            },
            initRender);
            initRender = null;
        },

        initFirst: function(res) {

            this.e = xGame.loadPopTmpl('lordsoul');
            
            this.bindAll();

            //初始化命格树
            this.initLordtree(res.list);

            //刷新资源
            this.refreshTopInfo(res);

            this.doSelectAnimation();

            this.currSelected = res.def_cost.id;
            this.e.find('.anibg').appendTo(this.e.find('#img'+this.currSelected));
            this.refreshRightInfo(res.def_cost, this.currSelected);
            this.autoScroll(this.currSelected);
            this.refreshBtnStatus(this.lordtreeScroller.currPageX);

            this.e.show();
            this.isshow = true;
            $('#worldloading').hide();
        },

        autoScroll: function(currselect) {
            var destpage = 0;

            if (parseInt(currselect)%10 == 0) {
                destpage = parseInt((parseInt(currselect)-1)/10);
            } else {
                destpage = parseInt(parseInt(currselect)/10);
            }

            if (destpage == this.lordtreeScroller.currPageX) {
                return false;
            }

            this.lordtreeScroller.scrollToPage(destpage, 0);
            this.refreshBtnStatus(destpage);
        },

        bindAll: function() {
            var that = this;

            //关闭按钮
            this.e.find('#lordsoul_hidebtn').unbind('touchend').bind('touchend', function(e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            //玩法说明按钮
            this.e.find('#lordsoulinfo_btn').unbind('touchend').bind('touchend', function(e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#lordsoulpop_info').css('display', 'block');
                e.preventDefault();
            });

            this.e.find('#lordsoulpop_info').find('.mask').unbind('touchend').bind('touchend', function(e) {
 
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#lordsoulpop_info').css('display', 'none');
                e.preventDefault();
            });

            //玩法说明关闭按钮
            this.e.find('#lordsoulinfo_hidebtn').unbind('touchend').bind('touchend', function(e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#lordsoulpop_info').css('display', 'none');
                e.preventDefault();
            });

            // 命格项点击事件
            this.e.find('#lordsoul_scroller').undelegate('.img', 'click').delegate('.img', 'click', function (e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                var uid = $(this).attr('uid');
                if (that.currSelected == uid || that.isanimating || that.islevelup) {
                    return false;
                }
                that.currSelected = uid;
                //刷新右侧信息
                var index = that.currSelected-10*that.lordtreeScroller.currPageX;
                var info = that.lordsoularray[that.lordtreeScroller.currPageX][index];
                if (!info) {
                    that.islevelup = false;
                    that.isanimating = false;
                    return false;
                }
                that.refreshRightInfo(info, that.currSelected);
                that.e.find('.anibg').appendTo(that.e.find('#img'+that.currSelected));

                e.preventDefault();
            });
            
            //升级按钮
            this.e.find('#lordsoullevelup_btn').unbind('touchend').bind('touchend', function(e) {

                if (that.e.find('#lordsoullevelup_btn').hasClass('disabled') || that.isanimating || that.islevelup) {
                    return false;
                }

                if (xGame.checkLock()) {
                    return false;
                }
                
                that.e.find('#lordsoullevelup_btn').removeClass('disabled').addClass('disabled');
                var id = $(this).attr('starid');

                that.doLevelup(id);

                e.preventDefault();
            });
            
            //提炼星魂按钮
            this.e.find('#lordsoulrefine_btn').unbind('touchend').bind('touchend', function(e) {

                if (xGame.checkLock()) {
                    return false;
                }

                xGame.showpop('soulrefine');
                e.preventDefault();
            });

            //主公命格scroll滑动事件处理
            this.e.undelegate('.box', 'touchstart').delegate('.box', 'touchstart', function(e){

                if (that.isanimating || xGame.checkLock(true)) {
                    return false;
                }

                if (e.touches.length == 1) {
                    that.ismoving = false;
                    var touch = e.touches[0];
                    that.currpoint = touch.pageX;
                }

                e.preventDefault();
            });

            this.e.undelegate('.box', 'touchmove').delegate('.box', 'touchmove', function(e){

                if (that.isanimating || xGame.checkLock(true)) {
                    return false;
                }

                if (e.touches.length == 1) {
                    var touch = e.touches[0];
                    var offset = touch.pageX - that.currpoint;
                    if (offset > 10) {//向右划
                        var currindex = that.lordtreeScroller.currPageX - 1;
                        if (currindex < 0 || that.ismoving) {
                            return false;
                        }
                        that.ismoving = true;
                        that.refreshBtnStatus(currindex);
                    } else if (offset < -10) {//向左划
                        var currindex = that.lordtreeScroller.currPageX + 1;
                        if (currindex > 2 || that.ismoving) {
                            return false;
                        }
                        that.ismoving = true;
                        that.refreshBtnStatus(currindex);
                    } 
                }
                e.preventDefault();
                
            });
            
            this.e.undelegate('.box', 'touchend').delegate('.box', 'touchend', function (e) {

                if (that.isanimating || xGame.checkLock(true)) {
                    return false;
                }

                that.ismoving = false;
                that.refreshBtnStatus(that.lordtreeScroller.currPageX);
                e.preventDefault();
            });

        },

        doLevelup: function(id) {
            var that = this;

            this.autoScroll(id);
            this.refreshBtnStatus(this.lordtreeScroller.currPageX);
            this.islevelup = true;

            var initRender = function(res) {
                switch (res.status) {
                    case 1:
                        that.isanimating = true;
                        that.e.find('#top_info').find('#lord_num1').html(that.getWan(res.star1));
                        that.e.find('#top_info').find('#lord_num2').html(that.getWan(res.star2));
                        that.e.find('#top_info').find('#lord_num3').html(that.getWan(res.star3));
                        that.allstar1 = res.star1;
                        that.allstar2 = res.star2;
                        that.allstar3 = res.star3;
                        var level = that.e.find('#img'+id).attr('level');
                        if (parseInt(level) <= res.next_cost.max_level) {
                            if (parseInt(level) < res.next_cost.max_level) {
                                that.e.find('#img'+id).attr({'level':parseInt(level)+1});
                            } else {
                                that.e.find('#img'+id).attr({'level':parseInt(level)});
                            }
                            if (id-10*that.lordtreeScroller.currPageX >= 0) {
                                that.lordsoularray[that.lordtreeScroller.currPageX][id-10*that.lordtreeScroller.currPageX] = res.next_cost;
                            }
                            level = that.e.find('#img'+id).attr('level');
                            if (parseInt(level) == res.next_cost.max_level) {
                                that.doBoomAnimation(res, id, 1);
                            } else {
                                that.doBoomAnimation(res, id, 2);
                            }
                        }
                        that.e.find('#img'+id).find('.leveltext').html(parseInt(that.e.find('#img'+id).attr('level'))+'/'+res.next_cost.max_level);
                        if (id == 30) {//最后一个特殊处理
                            var info = that.lordsoularray[that.lordtreeScroller.currPageX][10];
                            if (!info) {
                                return false;
                            }
                            that.refreshRightInfo(info, that.currSelected);
                        }
                        break;
                    default:
                        that.e.find('#lordsoullevelup_btn').removeClass('disabled');
                        that.islevelup = false;
                        that.isanimating = false;
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'star',
                'm': 'level_up',
                'starid':parseInt(id)
            },
            initRender);
            initRender = null;
        },

        refreshCurrCell: function(idx, bgimg, offset) {

            var cell = this.e.find('#img'+idx);
            var img = 'static/img/pop/lordsoul' + idx + '.png';
            var index = idx-10*this.lordtreeScroller.currPageX;

            if (index >= 0 && this.lordsoularray[this.lordtreeScroller.currPageX][index]) {
                this.lordsoularray[this.lordtreeScroller.currPageX][index].open_status = 1;
                var cellinfo = this.lordsoularray[this.lordtreeScroller.currPageX][index];
                var leveltext = cellinfo.level + '/' + cellinfo.max_level;
            
                cell.attr({'level':cellinfo.level});
            }
            
            if (idx%10 != 0) {
                cell.css({'background':'url('+ bgimg +') '+offset+'px '+offset+'px no-repeat'});
            }

            cell.find('.soulitem').css({'background':'url('+ img +') 9px 9px no-repeat'});
        },

        openNextCell: function(list) {
            var n = 0;

            for (var i in list) {
                var idx = list[i];

                if (idx <= 0) {
                    continue;
                }

                var bgimg = staticUrl + 'img/pop/lordsoul_bg2.png';
                
                if (n == 0) {
                    this.currSelected = parseInt(idx);
                    this.autoScroll(this.currSelected);
                }
                this.refreshCurrCell(idx, bgimg, 9);
                this.e.find('#img'+idx).find('.levelbg').find('.leveltext').removeClass('yean').addClass('red');

                if (n == 0) {
                    this.e.find('.anibg').appendTo(this.e.find('#img'+this.currSelected));
                    var levelupbtn = this.e.find('#lordsoullevelup_btn');
                    levelupbtn.attr({'starid':this.currSelected});
                    this.doOpenCellAnimation();
                }

                n++;
            }

            if (list.length == 0) {
                this.islevelup = false;
                this.isanimating = false;
                var index = this.currSelected-10*this.lordtreeScroller.currPageX;
                var info = this.lordsoularray[this.lordtreeScroller.currPageX][index];
                if (info) {
                    this.refreshRightInfo(info, this.currSelected);
                }
            }
        },

        //刷新scroll左右滑动按钮
        refreshBtnStatus: function(index) {
            if (index > 0 && index < this.lordsoularray.length-1) {
                this.e.find('#scroller_btn_right').show();
                this.e.find('#scroller_btn_left').show();
            } else if (index <= 0) {
                this.e.find('#scroller_btn_right').show();
                this.e.find('#scroller_btn_left').hide();
            } else {
                this.e.find('#scroller_btn_right').hide();
                this.e.find('#scroller_btn_left').show();
            }
        },

        refreshTopInfo: function(res) {
            this.e.find('#top_info').find('#lord_num1').html(this.getWan(res.all_star1));
            this.e.find('#top_info').find('#lord_num2').html(this.getWan(res.all_star2));
            this.e.find('#top_info').find('#lord_num3').html(this.getWan(res.all_star3));
            this.allstar1 = res.all_star1;
            this.allstar2 = res.all_star2;
            this.allstar3 = res.all_star3;
        },

        refreshRightInfo: function(info, id) {

            if (!info) {
                return false;
            }

            //图片
            this.e.find('#right_info').find('.soulimg').remove();
            var item = '<span class="soulimg" style="background:url(static/img/pop/lordsoul' + id + '.png' +') 9px 9px no-repeat, url('+ staticUrl + 'img/pop/lordsoul_bg2.png' +') 0 0 no-repeat"></span>';
            this.e.find('#right_info').append(item);
            //名字
            this.e.find('#right_info').find('#selectname').html(info.name);
            //当前等级
            var level = this.e.find('#img'+id).attr('level');
            this.e.find('#right_info').find('#currlevel').html('LV'+level);
            //属性
            this.e.find('#right_info').find('#currproperty').html(info.desc);

            //升级条件
            if (parseInt(info.prepos_level) > player.info.level) {
                this.e.find('#right_info').find('#upcondition').removeClass('red').addClass('red').html(lang('lordsoul_needlv', [info.prepos_level]));
            } else {
                this.e.find('#right_info').find('#upcondition').removeClass('red').html(lang('lordsoul_needlv', [info.prepos_level]));
            }

            if (this.islevelup) {
                this.doLightAnimation(this.e.find('#right_info').find('#upcondition'), 75, 180, staticUrl + 'img/pop/lordsoul_light_short.png');
            }
            
            if (info.cost.length == 0) {
                this.e.find('#right_info').find('#icon1_cost').hide();
                this.e.find('#right_info').find('#icon2_cost').hide();
                this.e.find('#right_info').find('#icon3_cost').hide();
                this.e.find('#right_info').find('.costnone').html(lang('no'));
                this.e.find('#right_info').find('.costnone').show();
            } else {
                this.e.find('#right_info').find('.costnone').hide();
            }

            //升级消耗--幽蓝
            if (parseInt(info.cost.value1) <= 0) {
                this.e.find('#right_info').find('#icon1_cost').hide();
            } else if(parseInt(info.cost.value1) > 0) {

                if (parseInt(this.allstar1) < parseInt(info.cost.value1)) {
                    this.e.find('#right_info').find('#icon1cost').removeClass('red').addClass('red').html(info.cost.value1);
                } else {
                    this.e.find('#right_info').find('#icon1cost').removeClass('red').html(info.cost.value1);
                }

                this.e.find('#right_info').find('#icon1_cost').show();

                if (this.islevelup) {
                    if (parseInt(info.cost.value2) <= 0) {
                        this.doLightAnimation(this.e.find('#right_info').find('.upcost'), 75, 180, staticUrl + 'img/pop/lordsoul_light_short.png');
                    } else {
                        this.doLightAnimation(this.e.find('#right_info').find('.upcost'), 75, 320, staticUrl + 'img/pop/lordsoul_light_long.png');
                    }
                }
            }
            
            //铭黄
            if (parseInt(info.cost.value2) <= 0) {
                this.e.find('#right_info').find('#icon2_cost').hide();
            } else if (parseInt(info.cost.value2) > 0) {
                if (parseInt(this.allstar2) < parseInt(info.cost.value2)) {
                    this.e.find('#right_info').find('#icon2cost').removeClass('red').addClass('red').html(info.cost.value2);
                } else {
                    this.e.find('#right_info').find('#icon2cost').removeClass('red').html(info.cost.value2);
                }
                this.e.find('#right_info').find('#icon2_cost').show();
            }

            //紫阳
            if (parseInt(info.cost.value3) <= 0) {
                this.e.find('#right_info').find('#icon3_cost').hide();
            } else if (parseInt(info.cost.value3) > 0) {
                if (parseInt(this.allstar3) < parseInt(info.cost.value3)) {
                    this.e.find('#right_info').find('#icon3cost').removeClass('red').addClass('red').html(info.cost.value3);
                } else {
                    this.e.find('#right_info').find('#icon3cost').removeClass('red').html(info.cost.value3);
                }
                this.e.find('#right_info').find('#icon3_cost').show();
                if (this.islevelup) {
                    this.doLightAnimation(this.e.find('#right_info').find('#icon3_cost'), -15, 180, staticUrl + 'img/pop/lordsoul_light_short.png');
                }
            }

            this.islevelup = false;
            this.isanimating = false;
            this.e.find('#lordsoullevelup_btn').removeClass('disabled');
            
            //升级按钮
            var levelupbtn = this.e.find('#lordsoullevelup_btn');
            levelupbtn.attr({'starid':id});
            if (info.open_status == 0) {
                levelupbtn.removeClass('disabled').addClass('disabled');
                levelupbtn.html(lang('level_up'));
            } else {
                levelupbtn.removeClass('disabled');
                if (level == info.max_level) {
                    levelupbtn.removeClass('disabled').addClass('disabled');
                    levelupbtn.html(lang('is_full_s'));
                } else {
                    levelupbtn.html(lang('level_up'));   
                }
            }
        },

        getWan: function (num) {

            var num = parseInt(num);

            if (num >= 100000) {
                return Math.floor(parseInt(num) / 10000) + lang('10k');
            } else {
                return num;
            }
        },

        initLordtree: function(list) {
            var that = this;
            for (var i in list) {
                this.lordsoularray.push(list[i]);
            }
            var html = this.getHtml(this.lordsoularray);
            this.e.find('#lordsoul_list').html(html).width(this.lordsoularray.length * 489 + 'px');
            html = null;

            if (this.lordtreeScroller == null) {
                this.lordtreeScroller = new iScroll('lordsoul_scroller', {
                    snap: 'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            
            this.lordtreeScroller.refresh();

            //绑定滑动门左右按钮
            this.e.find('#scroller_btn_left').unbind('touchend').bind('touchend', function() {

                if (that.isanimating || xGame.checkLock(true)) {
                    return false;
                }
                that.lordtreeScroller.scrollToPage('prev', 0);
                that.refreshBtnStatus(that.lordtreeScroller.currPageX);

                return false;
            });

            this.e.find('#scroller_btn_right').unbind('touchend').bind('touchend', function() {

                if (that.isanimating || xGame.checkLock(true)) {
                    return false;
                }
                that.lordtreeScroller.scrollToPage('next', 0);
                that.refreshBtnStatus(that.lordtreeScroller.currPageX);

                return false;
            });
        },

        getHtml: function (list) {
            var html = '';
           
            for (var i = 0, len = list.length; i < len; i++) {
                var pageitem = list[i];
                html += '<li>';
                html += '<div class="box">';

                for (var j in pageitem) {
                    var lorditem = pageitem[j];
                    var frontimg = '';
                    var bgimg = '';
                    var offsetx = 0;
                    var offsety = 0;

                    if (lorditem.open_status == 1) {//技能开启
                        img = 'static/img/pop/lordsoul' + lorditem.id + '.png';

                        if (lorditem.level == lorditem.max_level) {//满级
                            bgimg = staticUrl + 'img/pop/lordsoul_bg3.png';
                        } else {
                            bgimg = staticUrl + 'img/pop/lordsoul_bg2.png';
                            offsetx = 12;
                            offsety = 9;
                        }
                    } else if (lorditem.open_status == 0) {//未开启
                        img = 'static/img/pop/lordsoul' + lorditem.id + '_1.png';
                        bgimg = staticUrl + 'img/pop/lordsoul_bg1.png';
                        offsetx = 12;
                        offsety = 9;
                    }

                    var leveltext = lorditem.level + '/' + lorditem.max_level;
                    var colorclass = 'red';
                    if (parseInt(lorditem.level) == parseInt(lorditem.max_level)) {
                        colorclass = 'green';
                    } else if (lorditem.open_status == 0) {
                        colorclass = 'yean';
                    }

                    if (j != 10) {
                        html += '<div level="'+lorditem.level+'" class="img pos'+j+'" id="img'+lorditem.id+'" uid="'+lorditem.id+'" style="background:url('+ bgimg +') '+offsetx+'px '+offsety+'px no-repeat">';
                        html += '<span class="soulitem" style="background:url('+ img +') 9px 9px no-repeat"></span>';
                        html += '<span class="levelbg type1"><em class="leveltext '+colorclass+'">'+leveltext+'</em></span>';
                        html += '</div>';
                    } else {//最下方一个技能
                        bgimg = staticUrl + 'img/pop/lordsoul_bg4.png';
                        html += '<div level="'+lorditem.level+'" class="img pos'+j+'" id="img'+lorditem.id+'" uid="'+lorditem.id+'" style="background:url('+ bgimg +') 0 0 no-repeat">';
                        html += '<span class="soulitem" style="background:url('+ img +') 9px 9px no-repeat"></span>';
                        html += '<span class="levelbg type2"><em class="leveltext '+colorclass+'">'+leveltext+'</em></span>';
                        html += '</div>';
                    }
                }
                html += '</div></li>';
            }

            return html;
        },

        doSelectAnimation: function() {
            var mc = new movieClipClass({
                "img": staticUrl + 'img/pop/lordsoul_select_animation.png',
                "width": 110,
                "height": 110,
                "x": 0,
                "y": 0,
                "scale": 1,
                "loop": true,
                "endDestory": true,
                "callback": function() {
                },
                "frames": [{
                    rect: [0, 0],
                    interval: 100
                },
                {
                    rect: [110, 0],
                    interval: 100
                },
                {
                    rect: [220, 0],
                    interval: 100
                },
                {
                    rect: [330, 0],
                    interval: 100
                },
                {
                    rect: [440, 0],
                    interval: 100
                },
                {
                    rect: [550, 0],
                    interval: 100
                },
                {
                    rect: [0, 110],
                    interval: 100
                },
                {
                    rect: [110, 110],
                    interval: 100
                },
                {
                    rect: [220, 110],
                    interval: 100
                },
                {
                    rect: [330, 110],
                    interval: 100
                },
                {
                    rect: [440, 110],
                    interval: 100
                },
                {
                    rect: [0, 220],
                    interval: 100
                },
                {
                    rect: [110, 220],
                    interval: 100
                },
                {
                    rect: [220, 220],
                    interval: 100
                },
                {
                    rect: [330, 220],
                    interval: 100
                },
                {
                    rect: [440, 220],
                    interval: 100
                },
                {
                    rect: [550, 220],
                    interval: 100
                },
                {
                    rect: [0, 330],
                    interval: 100
                },
                {
                    rect: [110, 330],
                    interval: 100
                },
                {
                    rect: [220, 330],
                    interval: 100
                },
                {
                    rect: [330, 330],
                    interval: 100
                },
                {
                    rect: [440, 330],
                    interval: 100
                },
                {
                    rect: [550, 330],
                    interval: 100
                },
                {
                    rect: [0, 440],
                    interval: 100
                },
                {
                    rect: [110, 440],
                    interval: 100
                },
                {
                    rect: [220, 440],
                    interval: 100
                },
                {
                    rect: [330, 440],
                    interval: 100
                },
                {
                    rect: [440, 440],
                    interval: 100
                },
                {
                    rect: [550, 440],
                    interval: 100
                }]
            });

            mc.appendTo(this.e.find('.anibg'));
            mc.play();
            mc = null;
        },

        doBoomAnimation: function(res, id, type) {
            var that = this;
            var mc = new movieClipClass({
                "img": staticUrl + 'img/pop/lordsoul_levelup.png',
                "width": 200,
                "height": 200,
                "x": -55,
                "y": -55,
                "scale": 1,
                "loop": false,
                "endDestory": true,
                "callback": function() {

                    if (type == 1) {
                        //刷新当前命格显示
                        that.e.find('#img'+id).find('.leveltext').removeClass('green').addClass('green');
                        var bgimg = staticUrl + 'img/pop/lordsoul_bg3.png';
                        that.refreshCurrCell(id, bgimg, 0);
                        //开启下一命格
                        that.openNextCell(res.open_star_list);
                    } else if (type == 2) {
                        that.refreshRightInfo(res.next_cost, id);
                    }
                },
                "frames": [{
                    rect: [0, 0],
                    interval: 100
                },
                {
                    rect: [200, 0],
                    interval: 100
                },
                {
                    rect: [400, 0],
                    interval: 100
                },
                {
                    rect: [600, 0],
                    interval: 100
                },
                {
                    rect: [800, 0],
                    interval: 100
                },
                {
                    rect: [0, 200],
                    interval: 100
                },
                {
                    rect: [200, 200],
                    interval: 100
                },
                {
                    rect: [400, 200],
                    interval: 100
                },
                {
                    rect: [600, 200],
                    interval: 100
                },
                {
                    rect: [800, 200],
                    interval: 100
                }]
            });

            mc.appendTo(this.e.find('#right_info').find('.soulimg'));
            mc.play();
            mc = null;
        },

        doOpenCellAnimation: function() {
            var that = this;
            var mc = new movieClipClass({
                "img": staticUrl + 'img/pop/lordsoul_opencell.png',
                "width": 120,
                "height": 120,
                "x": -5,
                "y": -5,
                "scale": 1,
                "loop": false,
                "endDestory": true,
                "callback": function() {
                    //刷新右侧信息
                    var index = that.currSelected-10*that.lordtreeScroller.currPageX;
                    var info = that.lordsoularray[that.lordtreeScroller.currPageX][index];

                    if (info) {
                        that.refreshRightInfo(info, that.currSelected);
                    } else {
                        that.islevelup = false;
                        that.isanimating = false;
                    }
                },
                "frames": [{
                    rect: [0, 0],
                    interval: 100
                },
                {
                    rect: [120, 0],
                    interval: 100
                },
                {
                    rect: [240, 0],
                    interval: 100
                },
                {
                    rect: [360, 0],
                    interval: 100
                },
                {
                    rect: [0, 120],
                    interval: 100
                },
                {
                    rect: [120, 120],
                    interval: 100
                },
                {
                    rect: [240, 120],
                    interval: 100
                },
                {
                    rect: [360, 120],
                    interval: 100
                },
                {
                    rect: [0, 240],
                    interval: 100
                },
                {
                    rect: [120, 240],
                    interval: 100
                },
                {
                    rect: [240, 240],
                    interval: 100
                },
                {
                    rect: [360, 240],
                    interval: 100
                },
                {
                    rect: [0, 360],
                    interval: 100
                },
                {
                    rect: [120, 360],
                    interval: 100
                },
                {
                    rect: [240, 360],
                    interval: 100
                },
                {
                    rect: [360, 360],
                    interval: 100
                }]
            });

            mc.appendTo(this.e.find('#img'+this.currSelected));
            mc.play();
            mc = null;
        },
        
        doLightAnimation: function(dom, offsetX, width, img) {
            var that = this;
            var mc = new movieClipClass({
                "img": img,
                "width": width,
                "height": 50,
                "x": offsetX,
                "y": -15,
                "scale": 1,
                "loop": false,
                "zindex":10,
                "endDestory": true,
                "callback": function() {
                    that.isanimating = false;
                },
                "frames": [{
                    rect: [0, 0],
                    interval: 50
                },
                {
                    rect: [width, 0],
                    interval: 50
                },
                {
                    rect: [width*2, 0],
                    interval: 50
                },
                {
                    rect: [0, 50],
                    interval: 50
                },
                {
                    rect: [width, 50],
                    interval: 50
                },
                {
                    rect: [width*2, 50],
                    interval: 50
                },
                {
                    rect: [0, 100],
                    interval: 50
                },
                {
                    rect: [width, 100],
                    interval: 50
                },
                {
                    rect: [width*2, 100],
                    interval: 50
                },
                {
                    rect: [0, 150],
                    interval: 50
                },
                {
                    rect: [width, 150],
                    interval: 50
                },
                {
                    rect: [width*2, 150],
                    interval: 50
                },
                {
                    rect: [0, 200],
                    interval: 50
                },
                {
                    rect: [width, 200],
                    interval: 50
                },
                {
                    rect: [width*2, 200],
                    interval: 50
                },
                {
                    rect: [0, 250],
                    interval: 50
                },
                {
                    rect: [width, 250],
                    interval: 50
                },
                {
                    rect: [width*2, 250],
                    interval: 50
                }]
            });

            mc.appendTo(dom);
            mc.play();
            mc = null;
        },

        unbindAll: function() {
            this.e.find('#lordsoul_hidebtn').unbind('touchend');
            this.e.find('#lordsoulinfo_btn').unbind('touchend');
            this.e.find('#lordsoulpop_info').find('.mask').unbind('touchend');
            this.e.find('#lordsoulinfo_hidebtn').unbind('touchend');
            this.e.find('#lordsoul_scroller').undelegate('.img', 'click');
            this.e.find('#lordsoullevelup_btn').unbind('touchend');
            this.e.find('#lordsoulrefine_btn').unbind('touchend');
        },

        hide: function() {
            this.isshow = false;

            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }

            if (this.lordtreeScroller) {
                this.lordtreeScroller.destroy();
                this.lordtreeScroller = null;
            }
            this.lordsoularray = null;
            this.isanimating = false;
            this.islevelup = false;
            this.ismoving = false;
            this.currpoint = 0;
        }
    };
})();