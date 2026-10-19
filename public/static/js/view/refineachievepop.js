/*精炼成就*/
(function() {
    refineachievePopClass = function() {
        this.e;
        this.isshow = false;
        this.data;
        this.myScroll;
        this.myScroll2;
        this.bottomDom;
        this.rightDom;

        this.nowHeroId; //当前查看的武将id
        this.anim = [];
    };

    refineachievePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('get_hero_list'));
                    $('#worldloading').hide();
                    break;
                case -2:
                    xGame.toast.show(lang('refineachievepop_avatar_fail'));
                    $('#worldloading').hide();
                    break;
                case -3:
                    xGame.toast.show(lang('get_hero_info_fail'));
                    $('#worldloading').hide();
                    break;
                case 0:
                    xGame.toast.show(lang('refineachievepop_requst_fail'));
                    $('#worldloading').hide();
                    break;
                case 1:
                    /* 保存数据 */
                    that.data = res;
                    that.fillList();
                    xGame.showpop('system', {'pop': 'refineachieve'});
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                    break;
                }
            };
            loadContents({
                'c':'refine',
                'm': 'achieve_index'
            },
            initRender, true); //加载数据
            initRender = null;
        },

        avatarRequest: function() {
            var that = this;

            var initRender = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('get_hero_list'));
                    break;
                case -2:
                    xGame.toast.show(lang('refineachievepop_avatar_fail'));
                    break;
                case -3:
                    xGame.toast.show(lang('get_hero_info_fail'));
                    break;
                case 0:
                    xGame.toast.show(lang('refineachievepop_requst_fail'));
                    break;
                case 1:
                    /* 保存数据 */
                    that.data = res;
                    that.buildBottomLeft(res.general);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
            };
            loadContents({
                'c':'refine',
                'm': 'get_achieve_info',
                'gid': that.nowHeroId
            },
            initRender, true); //加载数据
            initRender = null;

        },

        fillList: function() {
            var that = this;

            that.e = xGame.loadPopTmpl('refineachieve');
            that.e.find('#hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.hide();
                xGame.showpop('equiprefine');
                e.preventDefault();
            });

            /* 顶部武将列表 */
            that.rightDom = that.e.find('#refineachieve_right');
            that.bottomDom = that.e.find('#refineachieve_left');
            var html = '';
            var n = 0;
            for (var i in that.data.list) {
                var item = that.data.list[i];
                html += '<li id="hero' + item.id + '" class="q' + item.quality + ' heroitem' + (n == 0 ? ' selected': '') + '" heroid="' + item.id + '" herolv="' + item.level + '" ><span class="avatar" style="background:url(' + xGame.avatar(item.image) + ') center center no-repeat;' + '"></span><p class="name">' + item.name + '</p></li>';
                n++;
            }
            that.e.find('#hero_list_scroller').html(html);

            /* 创建滑动门 domid, 单页宽度 */
            that.myScroll = xGame.scroller('heropop_scroller', n * 120);

            /*第一次通过index接口获得的数据看将领信息*/
            that.buildBottomLeft(that.data.general);

            that.bindAll();
            that.e.show();
            $('#worldloading').hide();
        },

        /*显示左边玩家信息，以及道具装备*/
        buildBottomLeft: function(data) {
            var that = this;

            var html = '';
            /*四个装备信息*/
            for (var i = 1; i <= 4; i++) {
                if (data['eid' + i] != '0' && data['eid' + i] != undefined) {
                    var _color = helper.getItemColor(data['eid' + i].quality);
                    html += '<div class="item' + i + ' moveitem" eid="' + i + '">';
                    html += '<div class="pic" style="background-image:url(' + xGame.itemurl(data['eid' + i].equipid) + ');background-size:95px"></div>';
                    html += (data['eid' + i].hammer == 0 ? '' : '<span class="refineinheritpop_hammer hammer' + data['eid' + i].hammer_quality + '">' + data['eid' + i].hammer + '</span>');
                    html += '</div>';
                    that.bottomDom.find('#eid' + i).attr({
                        "class": "eid" + i + ' c_s_i_bj' + data['eid' + i].quality
                    }); //已有装备的格子，删除装备背景
                } else {
                    that.bottomDom.find('#eid' + i).attr({
                        "class": "eid" + i + ' eidbg' + i
                    });
                }
            }

            that.bottomDom.find('#hero_item_box').html(html);

            /*武将信息*/
            that.bottomDom.find('#hero_type').html(helper.getItemVocation(data.type));
            that.bottomDom.find('#hero_lv').html('Lv.' + data.level);
            that.bottomDom.find('#hero_name').html(data.name);
            if (data.turn > 0) {
                that.bottomDom.find('#hero_turn').html(data.turn).show();
            } else {
                that.bottomDom.find('#hero_turn').hide();
            }
            that.bottomDom.find('#hero_img').css('backgroundImage', 'url(' + xGame.avatar(data.image, true) + ')');          
            that.bottomDom.find('#hero_body').attr('class', 'hero_body q' + data.quality);
            if(data.achid > 0) {
                var achieve = that.data.achList;
                var wuli = 0;
                var zhili = 0;
                var p_attack = 0;
                var m_attack = 0;

                for(var i = 0; i < data.achid; i++) {
                    wuli += parseInt(achieve[i].add_type_val1);
                    zhili += parseInt(achieve[i].add_type_val2);
                    p_attack += parseInt(achieve[i].add_type_val3);
                    m_attack += parseInt(achieve[i].add_type_val4);
                }

                that.bottomDom.find('#achieve_level').html('<span id="achieve_name_left" style="color:' + helper.getItemColor(achieve[i - 1].id) + '">' + achieve[i - 1].name + '</span>');

                var html2 = '<div class="achieve_info_text">' + lang('wuli') + '<em class="green">+' + wuli + '</em>&nbsp;&nbsp;' + lang('p_attack') + '<em class="green">+' + p_attack + '</em></div>';
                html2 += '<div class="achieve_info_text">' + lang('zhili') + '<em class="green">+' + zhili + '</em>&nbsp;&nbsp;' + lang('m_attack') + '<em class="green">+' + m_attack + '</em></div>';
            } else {
                var html2 = '<div class="achieve_info_text">' + lang('refineachievepop_not_achieve') + '</div>';
                that.bottomDom.find('#achieve_level').html('');
            }
            that.bottomDom.find('#achieve_value_list').html(html2);

            /*创建右端成就列表*/
            var html1 = '';
            var n = 0;
            for(var i in that.data.achList) {
                var achieve = that.data.achList[i];
                html1 += '<li class="achieve_list_li" id="achieve' + achieve.id + '">';
                html1 += '<span id="achieve_name" style="color:' + helper.getItemColor(achieve.id) + '">' + achieve.name + '</span>';
                html1 += '<div class="achieve_info_text">' + lang('refineachievepop_reach_level', [achieve.level]) + '</div>';
                html1 += '<div class="achieve_info_text">' +lang('refineachievepop_reach_reward') + lang('wuli') + '<em class="green">+' + achieve.add_type_val1 + '</em>&nbsp;&nbsp;' + lang('p_attack') + '<em class="green">+' + achieve.add_type_val3 + '</em></div>';
                html1 += '<div class="achieve_info_text" id="achieve_info_text2">' + lang('zhili') + '<em class="green">+' + achieve.add_type_val2 + '</em>&nbsp;&nbsp;' + lang('m_attack') + '<em class="green">+' + achieve.add_type_val4 + '</em></div>';
                if(that.data.general.achid > i ) {
                    html1 += '<span class="achieve_img"></span>';
                }
                html1 += '</li>';
            }
            that.rightDom.find('#refineachieve_list_scroller').html(html1);

            that.myScroll2 = new iScroll('refineachieve_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            that.myScroll2.refresh();

            that.hideAnim();
            that.playAnim(that.bottomDom.find('#hero_body'), data.achid);
        },

        bindAll: function() {

            var that = this;

            //绑定滑动门左右按钮
            that.e.find('#scroller_btn_left').unbind('touchend').bind('touchend',
            function() {
                that.myScroll.scrollToPage('prev', 0);
                return false;
            });
            that.e.find('#scroller_btn_right').unbind('touchend').bind('touchend',
            function() {
                that.myScroll.scrollToPage('next', 0);
                return false;
            });

            /* 绑定武将头像 */
            that.e.find('#hero_list_scroller').undelegate('li', 'click').delegate('li', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var newHeroid = $(this).attr('heroid');

                if (newHeroid == that.nowHeroId) {
                    return false;
                }
                if(that.nowHeroId == undefined) {
                    that.e.find('#hero_list_scroller').find('li').removeClass('selected');
                }
                that.e.find('#hero' + that.nowHeroId).removeClass('selected');
                $(this).addClass('selected');
                that.nowHeroId = newHeroid;
                that.avatarRequest();
            });

            /*玩法说明*/
            that.rightDom.undelegate('#refineachieve_info_btn', 'touchend').delegate('#refineachieve_info_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#refineachievepop_small').css('display', 'block');
            });
            that.e.find('#refineachievepop_small_hide').unbind('touchend').bind('touchend', function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#refineachievepop_small').css('display', 'none');
            });

        },

        playAnim: function(obj, quality) {
            var that = this;

            if (parseInt(quality) == 0) {
                return false;
            }

            that.anim = [];

            // 动画位置数据
            var animPos = [
                [-14, -14],
                [-14, 117],
                [276, -14],
                [276, 117]
            ];

            for (var i = 0; i < 4; i++) {

                that.anim[i] = new movieClipClass({
                    "img": ximg(staticUrl + 'img/pop/refine_animate' + quality + '.png'),
                    "width": 130,
                    "height": 130,
                    "x": animPos[i][0],
                    "y": animPos[i][1],
                    "loop": true,
                    "frames": [{
                        rect: [0, 0],
                        interval: 80
                    },
                    {
                        rect: [130, 0],
                        interval: 80
                    },
                    {
                        rect: [260, 0],
                        interval: 80
                    },
                    {
                        rect: [390, 0],
                        interval: 80
                    },
                    {
                        rect: [520, 0],
                        interval: 80
                    },
                    {
                        rect: [0, 130],
                        interval: 80
                    },
                    {
                        rect: [130, 130],
                        interval: 80
                    },
                    {
                        rect: [260, 130],
                        interval: 80
                    },
                    {
                        rect: [390, 130],
                        interval: 80
                    },
                    {
                        rect: [520, 130],
                        interval: 80
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
            for (var i = 0; i < 4; i++) {
                if (this.anim[i]) {
                    this.anim[i].destory();
                }
            }
            this.anim = [];
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#hidebtn').unbind('touchend');
                this.e.find('#scroller_btn_left').unbind('touchend');
                this.e.find('#scroller_btn_right').unbind('touchend');
                this.e.find('#refineachievepop_small_hide').unbind('touchend');
                this.e.find('#hero_list_scroller').undelegate('li', 'click');
                this.e.remove();
                this.e = null;
            }
            if (this.rightDom) {
                this.rightDom.undelegate('#refineachieve_info_btn', 'touchend');
                this.rightDom.remove();
                this.rightDom = null;
            }
        },

        hide: function() {

            xGame.unlock();
            this.unbindAll();
            this.isshow = false;
            this.data = null;
            this.hideAnim();
            if (this.myScroll) {
                this.myScroll.destroy();
                delete this.myScroll;
            }
            if (this.myScroll2) {
                this.myScroll2.destroy();
                delete this.myScroll2;
            }
            this.nowHeroId = null;
            if (this.bottomDom) {
                this.bottomDom.remove();
                this.bottomDom = null;
            }
            
        },
    };
})();