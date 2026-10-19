/* 资料 */
(function() {
    infoPopClass = function() {
        this.e;
        this.isshow = false;
        this.gid;
        this.uid;
        this.lastbookuid = 0;
        this.lasttalentuid = 0;
        this.lastgid = 0;
        this.talentdata = null;
        this.souldata = null;
        this.gemdata = null;
        this.lastfunc = null;
        this.privateName = '';
        this.myScroller;
        this.url;
        this.tree;
        this.horsedata;//坐骑的资料
        this.tabScroller;
        this.anim = [];
        this.isTimeout = null;
        this.countrycollegedata = null;   //国家学院数据
        this.lastSelected = null;         //上次选中的左侧标签页
        this.collegeinfoScroller = null;  //国家学院技能scroller
        this.lordsouldata = null;         //主公命格数据
        this.lordtreeScroller = null;     //主公命格 scroller
        this.beautydata = null;           //铜雀台数据
        this.beautyinfoScroller = null;   //铜雀台美人scroller
        this.nowBeautyId = 1;             //当前选中的美人id
        this.gemScroll = null; //宝石scroll
    };

    infoPopClass.prototype = {
        init: function(data) {
            var that = this;
            //保存uid
            if (!data) {
                return;
            }
            
            if (data.url != undefined) {
                that.url = data.url;
                that.uid = data.uid;
            } else {
                that.uid = data;
            }
            
            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            //调用首次请求方法
            var initRender = function(res) {
                switch (parseInt(res.status)) {
                case 0:
                    xGame.toast.show(lang('can_not_view_user'));
                    $('#worldloading').hide();
                    break;
                case - 1 : xGame.toast.show(lang('get_user_info_fail'));
                    $('#worldloading').hide();
                    break;
                case - 2 : xGame.toast.show(lang('hero_not_exist'));
                    $('#worldloading').hide();
                    break;
                case - 3 : xGame.toast.show(lang('equip_not_exist'));
                    $('#worldloading').hide();
                    break;
                case 2:
                    xGame.toast.show(lang('hero_not_expedition'));
                    $('#worldloading').hide();
                    break;
                case 1:
                    that.buildPage(res);
                    break;
                }
            };
            that.sploadContents({
                'c':
                'information',
                'm': 'index',
                'uid': that.uid
            },
            initRender, true); //加载数据
            initRender = null;
        },
        
        //这里为了跨服所以要特殊处理
        sploadContents: function(data, func, hide) {
            //console.log(this.url);
            if (this.url != undefined) {
                if (hide != undefined) {
                    http(this.url + '&c=' + data.c + '&m=' + data.m + '', data, func, hide);
                } else {
                    http(this.url + '&c=' + data.c + '&m=' + data.m + '', data, func);
                }
            } else {
                if (hide != undefined) {
                    loadContents(data, func, hide);
                } else {
                    loadContents(data, func);
                }
            }
        },

        buildPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('info');

            //绑定关闭事件
            that.e.find('#infopop_hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                that.hide();
                e.preventDefault();
            });
            
            if (typeof res.tree != 'undefined') {
                that.tree = parseInt(res.tree);
            } else {
                that.tree = 0;
            }

            //成功操作(组装左边的公共信息)
            that.sotw(res);
            
            that.bindAll(res);

            if (that.uid == player.info.uid || that.url != undefined) {
                that.e.find('.user_info').find('.btn').hide();
            } else {
                that.e.find('.user_info').find('.btn').show();
                
            }
            
            /* 暂时不需要滑动，需要滑动的时候再打开注释*/
            that.tabScroller = new iScroll('infopop_scroller', {
                snap: 'li',
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            that.tabScroller.refresh();
            
            //绑定滑动门左右按钮
            this.e.find('#info_scroller_btn_top').unbind('touchend').bind('touchend',
            function() {
                that.tabScroller.scrollToPage(0, 'prev');
                return false;
            });
            this.e.find('#info_scroller_btn_bottom').unbind('touchend').bind('touchend',
            function() {
                that.tabScroller.scrollToPage(0, 'next');
                return false;
            });

            that.refreshBtns('equip');

            that.e.show();
            $('#worldloading').hide();
        },
        
        bindAll: function(res) {
            var that = this;
            //载入数据（把数据保存到info里面）
            that.e.find('.left').find('.list').undelegate('.head', 'touchend').delegate('.head', 'touchend',
            function() {
                var _gid = $(this).attr('value');
                if (_gid == null || _gid == undefined || that.gid == _gid || _gid <= 0) {
                    return false;
                }

                //绑定的点击事件
                that.e.find('.left').find('.list').find('.head').removeClass('selected');
                $(this).addClass('selected');
                //请求数据
                that.gid = _gid;
                //区分哪些是主公的信息，那些是按照将领区分的
                if (that.lastfunc == 'soul_info') {
                    that.soul_info();
                } else if (that.lastfunc == 'gem_info') {
                    that.gem_info();
                } else if (that.lastfunc == 'mount_info') {
                    that.showHorse();
                } else if (that.lastfunc == 'countrycollege_info') {
                    that.collegeinfo();
                } else if (that.lastfunc == 'lordsoul_info') {
                    that.lordsoulinfo();
                } else if (that.lastfunc == 'beauty_info') {
                    that.beautyinfo();
                }  else {
                    that.viewHero();
                }
            });
            
            that.e.find('#infopop_list').undelegate('.cellbtn', 'click').delegate('.cellbtn', 'click', function() {
                var id = $(this).attr('id');
                if (id == 'talent') {
                    if (that.lastfunc == 'talent') {
                        return false;
                    }
                    that.lastfunc = 'talent';
                    that.talent();
                    that.refreshBtns(id);
                } else if (id == 'equip') {
                    if (that.lastfunc == 'general_info') {
                        return false;
                    }
                    that.viewHero();
                    that.lastfunc = 'general_info';
                    that.refreshBtns(id);
                } else if (id == 'soul') {
                    if (that.lastfunc == 'soul_info') {
                        return false;
                    }
                    that.lastfunc = 'soul_info';
                    that.souldata = null;
                    that.soul_info();
                    that.refreshBtns(id);
                } else if (id == 'general') {
                    if (that.lastfunc == 'generalbook_info') {
                        return false;
                    }
                    that.lastfunc = 'generalbook_info';
                    that.generalBook();
                    that.refreshBtns(id);
                } else if (id == 'gem') {
                    if (that.lastfunc == 'gem_info') {
                        return false;
                    }
                    that.lastfunc = 'gem_info';
                    that.gem_info();
                    that.refreshBtns(id);
                } else if (id == 'mount') {
                    if (that.lastfunc == 'mount_info') {
                        return false;
                    }
                    that.lastfunc = 'mount_info';
                    that.horsedata = null;
                    that.showHorse();
                    that.refreshBtns(id);
                } else if (id == 'countrycollege') {
                    if (that.lastfunc == 'countrycollege_info') {
                        return false;
                    }
                    that.lastfunc = 'countrycollege_info';
                    that.collegeinfo();
                    that.refreshBtns(id);
                } else if (id == 'lordsoul') {
                    if (that.lastfunc == 'lordsoul_info') {
                        return false;
                    }
                    that.lastfunc = 'lordsoul_info';
                    that.lordsoulinfo();
                    that.refreshBtns(id);
                } else if (id == 'beauty') {
                    if (that.lastfunc == 'beauty_info') {
                        return false;
                    }
                    that.lastfunc = 'beauty_info';
                    that.beautyinfo();
                    that.refreshBtns(id);
                }
            });

            //绑定事件
            that.e.find('#info_contact_btn').unbind('touchend').bind('touchend',
            function() {
                that.hide(function() {
                    //有mail和没有mail区别对待
                    if (mailboxpop == undefined || mailboxpop.e == null) {
                        console.log('当前没有mail');
                        xGame.showpop('mailbox', res.player.nickname);
                    } else {
                        console.log('当前有mail');
                        mailboxpop.sendFunc();
                        mailboxpop.bottomDom.find('#mb_write_all').find('#mb_receiver_input').val(res.player.nickname);
                    }

                });
            });

            that.e.find('#info_pk_btn').unbind('touchend').bind('touchend',
            function() {
                miscpop.battleByUid(that.uid);
            });

            that.e.find('#info_chat_btn').unbind('touchend').bind('touchend',
            function() {
                
                if (strategypop && strategypop.e) { //关掉攻略弹窗
                    strategypop.hide();
                }
                miscpop.hide('enemypop'); //关掉将领详情弹窗
                miscpop.hide('treasureenemypop'); //关掉金银洞将领详情弹窗

                if (typeof chatpop == 'undefined') {
                    xGame.showpop('chat');
                } else {
                    if (!chatpop.isshow) {
                        chatpop.init();
                    }
                }
                chatpop.chatType = 3;
                chatpop.privateUid = that.uid;
                chatpop.privateName = res.player.nickname;
                chatpop.bottomDom.find('#chat_type').find('#chattype_text').attr('class', 'chatType3').html(lang('private'));
                chatpop.bottomDom.find('#chat_from_all').find('div').html(lang('say_someone', [chatpop.privateName]));
                chatpop.bottomDom.find('#chat_from_all').addClass('is_private');
                chatpop.bottomDom.find('#chat_text').css('width', (570 - chatpop.bottomDom.find('#chat_from_all').find('div').width()) + 'px');
                chatpop.infoDom.css({
                    'display': 'none'
                });
                
                that.hide();
            });
            
            that.e.find('#info_gem_list').undelegate('li', 'touchend').delegate('li', 'touchend',
            function() {
                if (!$(this).hasClass('selected') && !$(this).hasClass('block')) {
                    var eid = $(this).attr('eid');
                    that.e.find('#info_gem_list').find('.selected').removeClass('selected');
                    $(this).addClass('selected');
                    that.showEightGem(that.gemdata['eid' + eid].gem, that.gemdata['eid' + eid].equipid, that.gemdata['eid' + eid].name);
                    eid = null;
                }
            });
            
            //绑定界面收起、查看按钮事件
            that.e.find('#g_book').undelegate('li .btn', 'touchend').delegate('li .btn', 'touchend', function() {
                if($(this).parents('li').hasClass('show')){
                    $(this).html(lang('champions_view'));
                    $(this).parents('li').removeClass('show');
                    $(this).parents('li').css({'height':'45px'});
                } else {
                    $(this).html(lang('infopop_hide'));
                    $(this).parents('li').addClass('show');
                    $(this).parents('li').css({'height':(45 + parseInt($(this).attr('num')) * 25)+'px'});
                }
                that.isTimeout = setTimeout(function() {
                    that.myScroller.refresh()
                }, 400);
            });
        },

        refreshBtns: function(id) {
            this.e.find('#'+id).parent().removeClass('selected').addClass('selected');
            if (this.lastSelected) {
                this.e.find('#'+this.lastSelected).parent().removeClass('selected');
            }
            this.lastSelected = id;
        },

        //铜雀台信息
        beautyinfo: function() {
            this.e.find('.right').find('#rt').show();
            this.e.find('.right').find('#infopop_rb').attr('class', 'beauty');
            var that = this;
            if (that.lastgid == that.gid && that.beautydata != null) {
                that.buildbeauty();
            } else {
                var Func = function(res) {
                    switch (parseInt(res.status)) {
                        case 1:
                            that.lastgid = that.gid;
                            that.beautydata = res.list;
                            that.buildbeauty();
                            break;
                        default : 
                            xGame.toast.show(res.msg);
                            break;
                    }
                };
                that.sploadContents({
                    'c': 'information',
                    'm': 'get_all_beauty_info',
                    'uid': that.uid
                },
                Func); //加载数据
                Func = null;
            }
        },

        buildbeauty: function() {

            var that = this;
            var html = '';
            var n = 0;
            
            for (var i = 0; i < this.beautydata.length; i++) {

                var item = this.beautydata[i];
                html += '<li class="beautyitem beauty'+item.beauty_id+' ' + (n == 0 ? 'selected' : '') + '" id="'+ (i + 1) +'">';
                html += '<span class="beautyimg" style="background-image: url('+ staticUrl + 'img/pop/beautypop_avatar' + item.beauty_id + '.png)"></span>';

                html += '<span class="level" style="color:'+ helper.getItemColor(item.quality) +'">'+ item.beauty_name +'</span>';
                html += '</li>';
                n++;
            }

            var length = (this.beautydata.length < 3) ? 3 : this.beautydata.length;

            if (this.beautydata.length < 3) {
                for (; i < 3; i++) {
                    html += '<li class="beautyitem">';
                    html += '</li>';
                };
            }

            if (this.beautydata.length == 0) {
                this.e.find('#nobeauty').css('display', 'block');
                this.e.find('#noskill').css('display', 'block');
            } else {
                this.e.find('#nobeauty').css('display', 'none');
                this.e.find('#noskill').css('display', 'none');
            }

            this.e.find('.right').find('#rt').show();
            this.e.find('.right').find('#infopop_rb').attr('class', 'beauty');

            this.e.find('#beautyinfo_list').html(html).width(length * 132 + 'px');

            if (this.beautyinfoScroller == null) {
                this.beautyinfoScroller = new iScroll('beautyinfo_scroller', {
                    snap: 'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.beautyinfoScroller.refresh();
            
            //
            if (this.beautydata.length != 0) {
                this.refreshBeautyInfo(0);
            }

            /* 绑定事件(必须在滑动门之后) */
            $('.beautyitem').unbind('click').bind('click',
            function() {
                var k = parseInt($(this).attr('id')) - 1;
                var data = that.beautydata[k];
                
                if(data) {
                    if (that.nowBeautyId != data.beauty_id) {
                        $('.beauty' + data.beauty_id).addClass('selected').siblings().removeClass('selected');
                        that.nowBeautyId = data.beauty_id;
                        that.refreshBeautyInfo(k);
                    }
                }
            });
        },

        refreshBeautyInfo: function(id) {
            var beautyinfo = this.beautydata[id];
            this.e.find('#beautyname').html(beautyinfo.beauty_name);
            this.e.find('#beautylv').html('LV' + beautyinfo.beauty_level);
            
            var html = '';
            for (var i = 0; i < beautyinfo.skill_info.length; i++) {
                var skill = beautyinfo.skill_info[i];
                html += '<li class="ownskill">' + skill.skill_name + '：' + skill.skill_content + skill.skill_value + '</li>';
            }

            this.e.find('.ownskill_bg').html(html);
        },

        //主公命格信息
        lordsoulinfo: function() {
            var that = this;
            if (that.lastgid == that.gid && that.lordsouldata != null) {
                that.buildlordsoul();
            } else {
                var Func = function(res) {
                    switch (parseInt(res.status)) {
                        case 1:
                            that.lastgid = that.gid;
                            that.lordsouldata = res;
                            that.buildlordsoul();
                            break;
                        default : 
                            xGame.toast.show(res.msg);
                            break;
                    }
                };
                that.sploadContents({
                    'c': 'information',
                    'm': 'get_star_info',
                    'uid': that.uid
                },
                Func); //加载数据
                Func = null;
            }
        },

        buildlordsoul: function() {
            this.e.find('.right').find('#rt').show();
            this.e.find('.right').find('#infopop_rb').attr('class', 'lordsoul');
            this.initLordtree(this.lordsouldata.list);
        },

        initLordtree: function(list) {
            var that = this;
            var lordsoularray = [];
            for (var i in list) {
                lordsoularray.push(list[i]);
            }
            var html = this.getHtml(lordsoularray);
            this.e.find('#info_lordsoul_list').html(html).width(lordsoularray.length * 372 + 'px');
            
            if (this.lordtreeScroller == null) {
                this.lordtreeScroller = new iScroll('info_lordsoul_scroller', {
                    snap: 'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.lordtreeScroller.refresh();

            this.refreshBtnStatus(0);
            //绑定滑动门左右按钮
            this.e.find('#info_scroller_btn_left').unbind('touchend').bind('touchend',
            function() {
                that.lordtreeScroller.scrollToPage('prev', 0);
                that.refreshBtnStatus(that.lordtreeScroller.currPageX);
                return false;
            });
            this.e.find('#info_scroller_btn_right').unbind('touchend').bind('touchend',
            function() {
                that.lordtreeScroller.scrollToPage('next', 0);
                that.refreshBtnStatus(that.lordtreeScroller.currPageX);
                return false;
            });
           
            this.e.undelegate('.box', 'touchstart').delegate('.box', 'touchstart', function(e){
                if (e.touches.length == 1) {
                    var touch = e.touches[0];
                    that.currpoint = touch.pageX;
                }
                e.preventDefault();
                e.stopPropagation();
            });

            this.e.undelegate('.box', 'touchmove').delegate('.box', 'touchmove', function(e){
                if (e.touches.length == 1) {
                    var touch = e.touches[0];
                    var offset = touch.pageX - that.currpoint;
                    if (offset > 10) {//向右划
                        var currindex = that.lordtreeScroller.currPageX - 1;
                        if (currindex < 0) {
                            return false;
                        }
                        that.refreshBtnStatus(currindex);
                    } else if (offset < -10) {//向左划
                        var currindex = that.lordtreeScroller.currPageX + 1;
                        if (currindex > 2) {
                            return false;
                        }
                        that.refreshBtnStatus(currindex);
                    } 
                }
                e.preventDefault();
                e.stopPropagation();
                
            });
            
            this.e.undelegate('.box', 'touchend').delegate('.box', 'touchend', function (e) {
                that.refreshBtnStatus(that.lordtreeScroller.currPageX);
                e.preventDefault();
                e.stopPropagation();
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
                    var size = 85;
                    var imgsize = 65;

                    var img = 'http://img2.hanjiangsanguo.com/hjimg/pop/lordsoul' + lorditem.id + '.png';

                    if (lorditem.level == lorditem.max_level) {
                        bgimg = staticUrl + 'img/pop/lordsoul_bg3.png';
                    } else if (lorditem.level > 0) {
                        bgimg = staticUrl + 'img/pop/lordsoul_bg2.png';
                        offsetx = 6;
                        offsety = 9;
                        imgsize = 70;
                        size = 75;
                    } else {
                        img = 'http://img2.hanjiangsanguo.com/hjimg/pop/lordsoul' + lorditem.id + '_1.png';
                        bgimg = staticUrl + 'img/pop/lordsoul_bg1.png';
                        offsetx = 5;
                        offsety = 9;
                        imgsize = 65;
                        size = 75;
                    }
                    
                    var leveltext = lorditem.level + '/' + lorditem.max_level;
                    
                    var colorclass = 'yean';
                    if (parseInt(lorditem.level) == parseInt(lorditem.max_level)) {
                        colorclass = 'green';
                    } else if (parseInt(lorditem.level) > 0) {
                        colorclass = 'red';
                    }

                    if (j != 10) {
                        html += '<div level="'+lorditem.level+'" class="img pos'+j+'" id="img'+lorditem.id+'" uid="'+lorditem.id+'" style="background:url('+ bgimg +') '+offsetx+'px '+offsety+'px no-repeat;background-size:'+size+'px">';
                        html += '<span class="soulitem" style="background:url('+ img +') 9px 12px no-repeat;background-size:'+imgsize+'px"></span>';
                        html += '<span class="levelbg type1"><em class="leveltext '+colorclass+'">'+leveltext+'</em></span>';
                        html += '</div>';
                    } else {
                        bgimg = staticUrl + 'img/pop/lordsoul_bg4.png';
                        html += '<div level="'+lorditem.level+'" class="img pos'+j+'" id="img'+lorditem.id+'" uid="'+lorditem.id+'" style="background:url('+ bgimg +') 0 0 no-repeat;background-size: 85px">';
                        html += '<span class="soulitem" style="background:url('+ img +') 9px 9px no-repeat;background-size:'+imgsize+'px"></span>';
                        html += '<span class="levelbg type2"><em class="leveltext '+colorclass+'">'+leveltext+'</em></span>';
                        html += '</div>';
                    }
                }
                html += '</div></li>';
            }

            return html;
        },

        refreshBtnStatus: function(index) {
            if (index > 0 && index < 2) {
                this.e.find('#info_scroller_btn_right').show();
                this.e.find('#info_scroller_btn_left').show();
            } else if (index <= 0) {
                this.e.find('#info_scroller_btn_right').show();
                this.e.find('#info_scroller_btn_left').hide();
            } else {
                this.e.find('#info_scroller_btn_right').hide();
                this.e.find('#info_scroller_btn_left').show();
            }
        },

        //显示国家学院信息
        collegeinfo: function() {
            var that = this;
            if (that.lastgid == that.gid && that.countrycollegedata != null) {
                that.buildcollege();
            } else {
                var Func = function(res) {
                    switch (parseInt(res.status)) {
                        case 1:
                            that.lastgid = that.gid;
                            that.countrycollegedata = res;
                            that.buildcollege();
                            break;
                        default : 
                            xGame.toast.show(res.msg);
                            break;
                    }
                };
                that.sploadContents({
                    'c': 'information',
                    'm': 'get_country_info',
                    'uid': that.uid
                },
                Func); //加载数据
                Func = null;
            }
        },
        
        buildcollege: function() {
            var that = this;
            var html = '';
            var n = 0;
            var temparray = [];
            for (var i = 0; i < this.countrycollegedata.skill_list.length; i++) {
                var skill = this.countrycollegedata.skill_list[i];
                if (parseInt(skill.skill_level) > 0) {
                    temparray.push(skill);
                }
            }
            for (var i = 0; i < temparray.length; i++) {
                var skill = temparray[i];
                html += '<li id="skill' + skill.skill_id + '" class="skillitem" skillkey="' + i + '">';
                html += '<span class="skillimg" style="background:url(http://img2.hanjiangsanguo.com/hjimg/pop/collegeskill'+skill.img_id + (skill.open==0 ? '_1.png' : '.png') + ') center center no-repeat;' + '"></span>';
                html += '<p class="level">LV' + skill.skill_level + '</p>';
                html += '</li>';
            }
            var length = (temparray.length < 3) ? 3 : temparray.length;
            if (temparray.length < 3) {
                for (; i < 3; i++) {
                    html += '<li class="skillitem">';
                    html += '</li>';
                };
            }
            
            this.e.find('.right').find('#rt').show();
            this.e.find('.right').find('#infopop_rb').attr('class', 'college');

            this.e.find('#collegeinfo_list').html(html).width(length * 132 + 'px');
            
            if (this.collegeinfoScroller == null) {
                this.collegeinfoScroller = new iScroll('collegeinfo_scroller', {
                    snap: 'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.collegeinfoScroller.refresh();

            //绑定滑动门左右按钮
            this.e.find('#collegeinfo_scroller_btn_left').unbind('touchend').bind('touchend',
            function() {
                that.collegeinfoScroller.scrollToPage('prev', 0);
                return false;
            });
            this.e.find('#collegeinfo_scroller_btn_right').unbind('touchend').bind('touchend',
            function() {
                that.collegeinfoScroller.scrollToPage('next', 0);
                return false;
            });

            this.initCollegeInfo();

            if (this.countrycollegedata.is_open == 1) {
                this.e.find('.collegeinfolimit').css('display', 'none');
                this.e.find('.collegeinfolimit2').css('display', 'none');
                this.e.find('.collegeinfocd').css('display', 'none');
                xGame.updateCd('collegeinfo', 0);
            } else {
                if (that.uid == player.info.uid) {
                    this.e.find('.collegeinfolimit').css('display', 'none');
                    this.e.find('.collegeinfolimit2').css('display', 'block');
                    this.e.find('.collegeinfocd').css('display', 'block');
                    xGame.updateCd('collegeinfo', this.countrycollegedata.cd);
                } else {
                    this.e.find('.collegeinfolimit').css('display', 'block');
                    this.e.find('.collegeinfolimit2').css('display', 'none');
                    this.e.find('.collegeinfocd').css('display', 'none');
                }
            }
        },

        initCollegeInfo: function() {
            var addition = this.countrycollegedata.skill_buffer.addition;
            if (!addition) {
                return;
            }
            this.e.find('#left_data_wrap').children('.wuli').children('span').text('+'+addition.wuli);
            this.e.find('#left_data_wrap').children('.zhili').children('span').text('+'+addition.zhili);
            this.e.find('#left_data_wrap').children('.tili').children('span').text('+'+addition.tili);
            this.e.find('#left_data_wrap').children('.shanbi').children('span').text('+'+addition.dodge);
            this.e.find('#left_data_wrap').children('.shipo').children('span').text('+'+addition.penetrate);
            this.e.find('#left_data_wrap').children('.mingzhong').children('span').text('+'+addition.mingzhong);

            this.e.find('#right_data_wrap').children('.wuligongji').children('span').text('+'+addition.attack);
            this.e.find('#right_data_wrap').children('.wulifangyu').children('span').text('+'+addition.defense);
            this.e.find('#right_data_wrap').children('.celuegongji').children('span').text('+'+addition.pattack);
            this.e.find('#right_data_wrap').children('.celuefangyu').children('span').text('+'+addition.pdefense);
            this.e.find('#right_data_wrap').children('.chushidouzhi').children('span').text('+'+addition.power);
            this.e.find('#right_data_wrap').children('.jingzhun').children('span').text('+'+addition.jingzhun);
        },

        //显示将魂信息
        soul_info: function() {
            var that = this;
            if (that.lastgid == that.gid && that.souldata != null) {
                that.buildsoul();
            } else {
                var Func = function(res) {
                    switch (parseInt(res.status)) {
                    case - 2 : xGame.toast.show(lang('info_fetch_soul_fail'));
                        break;
                    case - 1 : xGame.toast.show(lang('info_cannot_view_user_soul'));
                        break;
                    case 2 :
                        that.souldata = null;
                        that.lastgid = that.gid;
                        that.buildsoul();
                        break;
                    case 1:
                        that.souldata = res.general;
                        that.lastgid = that.gid;
                        that.buildsoul();
                        break;
                    default : xGame.toast.show(lang('info_cannot_view_soul'));
                        break;
                    }
                };
                that.sploadContents({
                    'c': 'information',
                    'm': 'get_own_soul',
                    'gid': that.gid,
                    'uid': that.uid
                },
                Func); //加载数据
                Func = null;
            }
        },

        buildsoul: function() {
            var that = this;
            var html = '';
            for (var i = 1; i <= 8; i++) {
                if (that.souldata == null || that.souldata['site' + i].islock == 1) {
                    that.e.find('#soulpos' + i).attr('class', 'closed');
                    that.e.find('#soulpos' + i).find('.soul_item').css({
                        'background': ''
                    });
                    that.e.find('#soulpos' + i).find('.level').css({
                        'opacity': 0
                    });
                } else {
                    that.e.find('#soulpos' + i).attr('class', 'open');
                    if (that.souldata['site' + i].info.length != 0) {
                        that.e.find('#soulpos' + i).find('.soul_item').css({
                            'background': 'url(http://img2.hanjiangsanguo.com/hjimg/pop/soul_' + that.souldata['site' + i].info.image + '.png)'
                        });
                        that.e.find('#soulpos' + i).find('.level').html(that.souldata['site' + i].info.level).css({
                            'opacity': 1
                        });
                    } else {
                        that.e.find('#soulpos' + i).find('.soul_item').css({
                            'background': ''
                        });
                        that.e.find('#soulpos' + i).find('.level').css({
                            'opacity': 0
                        });
                    }
                }
            }
            
            var dom = that.e.find('.right').find('#soulpop_lb');
            dom.find('#hero_img').css({
                'background': 'url(' + xGame.avatar(that.souldata.image, true) + ') 50% 50% no-repeat'
            });
            dom.find('#hero_type').html(helper.getItemVocation(that.souldata.type));
            dom.find('#hero_lv').html('Lv.' + that.souldata.level);
            dom.find('#hero_name').html(that.souldata.name);
            if (parseInt(that.souldata.turn) >= 1) {
                dom.find('#hero_turn').html(that.souldata.turn);
                dom.find('#hero_turn').css({
                    'display': 'block'
                });
            } else {
                dom.find('#hero_turn').css({
                    'display': 'none'
                });
            }
            dom.find('#body').attr('class', 'q' + that.souldata.quality);
            dom = null;
            
            that.e.find('.right').find('#rt').show();
            that.e.find('.right').find('#infopop_rb').attr('class', 'soul');
        },
        //显示天赋信息
        talent: function() {
            var that = this;

            if (that.talentdata != null && that.uid == that.lasttalentuid) {
                that.buildtalent();
            } else {
                var Func = function(res) {
                    switch (parseInt(res.status)) {
                    case - 2 : xGame.toast.show(lang('get_tf_info_fail'));
                        break;
                    case - 1 : xGame.toast.show(lang('can_not_view_tf'));
                        break;
                    case 0:
                        xGame.toast.show(lang('view_tf_later'));
                        break;
                    case 1:
                        that.talentdata = res.talent;
                        that.buildtalent();
                        that.lasttalentuid = that.uid;
                        break;
                    }
                };
                that.sploadContents({
                    'c': 'information',
                    'm': 'get_talent_list',
                    'uid': that.uid
                },
                Func); //加载数据
                Func = null;
            }
        },

        buildtalent: function() {
            var that = this;
            var html = '';
            for (var i = 0; i < that.talentdata.length; i++) {
                html += '<li class="tal tal' + (i + 1) + ' box' + that.talentdata[i].quality + '">';
                html += '<span class="pic" style="background-image:url(' + staticUrl + 'img/pop/talent_item' + that.talentdata[i].type + '.png);"></span>';
                html += '<span class="star">' + that.talentdata[i].star + '</span>';
                html += '</li>';
            }
            that.e.find('.right').find('#g_talent').find('ul').html(html);

            that.e.find('.right').find('#rt').show();
            that.e.find('.right').find('#infopop_rb').attr('class', 'talent');

        },

        /*请求成就信息*/
        generalBook: function() {
            var that = this;
            if (that.generalbookdata != null && that.uid == that.lastbookuid) {
                that.buildGeneralBook();
            } else {
                var Func = function(res) {
                    switch (parseInt(res.status)) {
                    case - 2 : xGame.toast.show(lang('get_tf_info_fail'));
                        break;
                    case - 1 : xGame.toast.show(lang('can_not_view_tf'));
                        break;
                    case 0:
                        xGame.toast.show(lang('view_tf_later'));
                        break;
                    case 1:
                        that.generalbookdata = res.achievement;
                        that.buildGeneralBook();
                        that.lastbookuid = that.uid;
                        break;
                    }
                };
                that.sploadContents({
                    'c': 'information',
                    'm': 'get_own_achievement',
                    'uid': that.uid
                },
                Func); //加载数据
                Func = null;
            }
        },

        /*组装成就信息*/
        buildGeneralBook: function() {
            var that = this;
            var html = '';
            //按排序字段排序
            infopop.generalbookdata.sort(function(x, y){
                return y.quality - x.quality;
            });
            var num;
            for(var i = 0; i < that.generalbookdata.length; i++) {
                num = 0;
                html += '<li><span class="title" style="color:'+helper.getItemColor(parseInt(that.generalbookdata[i].quality))+'">'+that.generalbookdata[i].name+'</span><div class="desc">';
                for(var j = 1; j <= 3; j++) {
                    if(parseInt(that.generalbookdata[i]['addvalue'+j]) > 0) {
                        num += 1;
                        html += '<span>'+that.generalbookdata[i]['addtype'+j]+': <em>+'+that.generalbookdata[i]['addvalue'+j]+'</em></span>';
                    }
                }
                html += '</div><button class="btn pop_btn_small" num="' + num + '">' + lang('champions_view') + '</button></li>';
            }
            that.e.find('#infopop_generalbook_scroller').find('ul').html(html);
            
            that.e.find('.right').find('#rt').show();
            that.e.find('.right').find('#infopop_rb').attr('class', 'generalbook');
            //滑动
            that.myScroller = new iScroll('infopop_generalbook_scroller', {
                snap: 'li',
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            that.myScroller.refresh();
        },

        sotw: function(res) {
            var that = this;
            //组装公共信息
            var html = '';
            for (var i = 1; i <= 8; i++) {
                if (res.list[i] != undefined) {
                    if (i == 1) { //第一个默认选中
                        that.gid = parseInt(res.list[i].id);
                    }
                    html += '<div class="head q' + res.list[i].quality + (i == 1 ? ' selected' : '') + '" value="' + res.list[i].id + '"><img src="' + xGame.avatar(res.list[i].image) + '"><div class="name">' + res.list[i].name + '</div></div>';
                } else {
                    html += '<div class="head" ></div>';
                }
            }
            that.e.find('.left').find('.list').html(html);
            //修改人物信息
            that.e.find('#name').find('#lv_wrap').html('Lv.' + res.player.level);
            that.e.find('#name').find('#name_wrap').html(res.player.nickname);
            
            if (res.friend != 1) {//不是好友
                that.e.find('#name').attr('class', 'add_friend').unbind('touchend').bind('touchend', function() {
                    miscpop.add_friend($(this), that.uid);
                });
            } else {
                that.e.find('#name').attr('class', 'is_friend');
            }
            that.e.find('#rank').find('em').html(res.player.rank);
            //玩家国家信息
            that.e.find('#mycountry').html(res.player.country ? res.player.country : '');
            //取数组第一个
            var firstdata = helper.array_first(res.list);
            //调用单个将领信息方法，组装数据
            if (firstdata) {
                //当前将领ID
                that.nowHeroId = firstdata.id;
                that.viewHero(firstdata);
            }
        },

        /*查看单个将领信息*/
        viewHero: function(data) {
            var that = this;
            if (data == undefined || data == null) {
                //请求接口
                var Func = function(res) {
                    switch (parseInt(res.status)) {
                    case - 1 : xGame.toast.show(lang('hero_not_exist'));
                        break;
                    case - 2 : xGame.toast.show(lang('parameter_error'));
                        break;
                    case 0:
                        xGame.toast.show(lang('get_hero_list'));
                        break;
                    case 2:
                        xGame.toast.show(lang('hero_not_expedition'));
                        break;
                    case 1:
                        that.buildBottomLeft(res.info);
                        break;
                    }
                    xGame.unlock();
                };
                that.sploadContents({
                    'c': 'information',
                    'm': 'get_info',
                    'gid': that.gid,
                    'uid': that.uid
                },
                Func); //加载数据
                Func = null;
            } else {
                //使用传过来的数据
                that.buildBottomLeft(data);
            }
        },

        /*组装单个将领信息*/
        buildBottomLeft: function(data) {
            var that = this;
            //把道具的节点删除
            var html = '';
            that.nowAnimPos = [];
            that.hammerQuality = [];
            that.hideAnim();

            /*四个装备信息*/
            for (var i = 1; i <= 4; i++) {
                that.e.find('#eid' + i).remove();
                if (data['eid' + i] != '0') {
                    var _color = helper.getItemColor(data['eid' + i].quality);
                    var hammer = data['eid' + i].hammer;
                    var hammerQuality = data['eid' + i].hammer_quality;

                    html += '<span id="eid' + i + '" eid="' + i + '" class="eid' + i + ' c_s_i_bj' + data['eid' + i].quality + '">';
                    html += '<div class="pic" style="background-image:url(' + xGame.itemurl(data['eid' + i].equipid) + ');"><em>' + lang('strong') + ':' + data['eid' + i].level + '</em>'+ (hammer != 0 ? '<span id="infopop_hammer" class="hammer' + hammerQuality + '">' + hammer + '</span>' : '') + '</div>';
                    html += '</span>';
                } else {
                    html += '<span id="eid' + i + '" eid="' + i + '" class="eid' + i + ' eidbg' + i + '"></span>';
                }
            }

            /* 将领信息 */
            //that.e.find('#hero_img').css({'background':'url('+ staticUrl +'img/avatar/a'+ data.image +'.jpg) 8px 8px no-repeat'});
            that.e.find('#hero_img').css({
                'background': 'url(' + xGame.avatar(data.image, true) + ') 50% 50% no-repeat'
            });
            that.e.find('#hero_type').html(helper.getItemVocation(data.type));
            that.e.find('#hero_lv').html('Lv.' + data.level);
            that.e.find('#hero_name').html(data.name);
            if (parseInt(data.turn) >= 1) {
                that.e.find('#hero_turn').html(data.turn);
                that.e.find('#hero_turn').css({
                    'display': 'block'
                });
            } else {
                that.e.find('#hero_turn').css({
                    'display': 'none'
                });
            }
            /* 属性信息 */
            that.e.find('#star_num').css({
                'width': parseInt(data.star) * 18 + 'px'
            });
            
            that.e.find('#hero_h').html(data.hp + (that.tree > 1 ? '<div id="infopop_tree" class="tree' + that.tree + '"></div>' : ''));
            that.e.find('#hero_a').html(data.wuli + ' +' + data.wuliup);
            that.e.find('#hero_t').html(data.talent.name == null ? lang('no') : data.talent.name);
            that.e.find('#hero_k').html(data.zhili + ' +' + data.zhiliup);
            if (data.skill.length != 0) {
                that.e.find('#hero_s').html(data.skill.name == null ? lang('no') : data.skill.name);
            }
            that.e.find('#hero_p').html(data.tili + ' +' + data.tiliup);
            //装载数据
            that.e.find('#hero_body').append(html);
            
            that.e.find('#hero_body').attr('class', 'hero_body q' + data.quality);
            
            that.e.find('.right').find('#rt').show();
            that.e.find('.right').find('#infopop_rb').attr('class', 'general');

            that.playAnim(that.e.find('#hero_body'), data.achID);
        },
        
        /*请求宝石信息*/
        gem_info: function() {
            var that = this;
            if (that.lastgid == that.gid && that.gemdata != null) {
                that.buildgem();
            } else {
                var Func = function(res) {
                    switch (parseInt(res.status)) {
                    case - 3 : xGame.toast.show(lang('beatengod_get_item_info_fail'));
                        break;
                    case - 2 : xGame.toast.show(lang('get_hero_info_fail'));
                        break;
                    case - 1 : xGame.toast.show(lang('parameter_error'));
                        break;
                    case 2 :
                        that.gemdata = null;
                        that.lastgid = that.gid;
                        that.buildgem();
                        break;
                    case 1 :
                        that.gemdata = res.info;
                        that.lastgid = that.gid;
                        that.buildgem();
                        break;
                    default : 
                        xGame.toast.show(lang('info_cannot_view_gem'));
                        break;
                    }
                };
                that.sploadContents({
                    'c': 'information',
                    'm': 'get_general_gem',
                    'gid': that.gid,
                    'uid': that.uid
                },
                Func); //加载数据
                Func = null;
            }
        },
        
        //构建4个装备
        buildgem: function() {
            var that = this;
            var html = '';
            var judge = true;
            
            var dom = that.e.find('.right');
            for (var i = 1,j = 1; i < 5; i ++) {
                if (that.gemdata == null || that.gemdata['eid' + i] == 0) {
                    if (i == 4 && judge) {
                        that.showEightGem(null);
                    }
                   
                } else {
                    dom.find('#gitem' + j).attr('class', 'c_i_bj' + that.gemdata['eid' + i].quality);
                    dom.find('#gitem' + j).find('span').css('background-image', 'url(' + xGame.itemurl(that.gemdata['eid' + i].equipid) + ')');
                    if (j == 1) {
                        dom.find('#gitem' + j).addClass('selected');
                        that.showEightGem(that.gemdata['eid' + i].gem, that.gemdata['eid' + i].equipid, that.gemdata['eid' + i].name);
                    }
                    dom.find('#gitem' + j).attr('eid', i);
                    j++;
                    judge = false;        
                }
            }
            
            for (var k = j; k < 5; k++) {
                dom.find('#gitem' + k).attr({
                    'class': 'c_i_bj1 block',
                    'eid': 0
                });
                dom.find('#gitem' + k).find('span').css('background-image','');
            }

            dom.find('#rt').hide();
            that.e.find('.right').find('#infopop_rb').attr('class', 'gem');

            
            //添加iscroll
            if (that.gemScroll == null) {
                that.gemScroll = new iScroll('gem_top', {
                    snap: 'li', 
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            } else {
                this.gemScroll.scrollTo(0, 0);
            }

            that.gemScroll.refresh();

            //绑定滑动门左右按钮
            this.e.find('#info_gem_ltbtn').unbind('touchend').bind('touchend',
            function() {

                if (that.gemScroll.x == 0) {
                    return false;
                }

                that.gemScroll.scrollToPage('prev', 0);
            });

            this.e.find('#info_gem_rtbtn').unbind('touchend').bind('touchend',
            function() {
                that.gemScroll.scrollToPage('next', 0);
            });
                 
            dom = null;
        },
        
        //显示8个宝石槽
        showEightGem: function(data, img, name){
            var dom = this.e.find('.right').find('#gem_bottom');
            for (var i = 1;i < 9;i ++) {
                if (data == undefined || data[i] == 0) {
                    dom.find('#gemlpos' + i).find('em').css('display', 'none');
                    dom.find('#gemlpos' + i).find('span').css('background-image', '');
                } else {
                    if (parseInt(data[i].level) > 0) {
                        dom.find('#gemlpos' + i).find('em').html(data[i].level).css('display', 'block');
                    } else {
                        dom.find('#gemlpos' + i).find('em').css('display', 'none');
                    }
                    dom.find('#gemlpos' + i).find('span').css('background-image', 'url(' + xGame.gem(data[i].type, data[i].quality) + ')');
                }
            }
            if (img != undefined) {
                dom.find('#item_img').css('background-image', 'url(' + xGame.itemurl(img) + ')').html('<em>' + name + '</em>');
            } else {
                dom.find('#item_img').css('background-image', '').html('');
            }
            dom = null;
        },
        
        showHorse: function() {
            var that = this;
            if (that.lastgid == that.gid && that.horsedata != null) {
                that.buildHorses();
            } else {
                var Func = function(res) {
                    switch (parseInt(res.status)) {
                        case - 1 : 
                            xGame.toast.show(lang('parameter_error'));
                            break;
                         case 1 :
                            that.lastgid = that.gid;
                            that.horsedata = res.general;
                            that.buildHorses();
                            break;
                        default : 
                            xGame.toast.show(lang('undefined_error'));
                            break;
                    }
                };
                that.sploadContents({
                    'c': 'information',
                    'm': 'get_general_mount',
                    'gid': that.gid,
                    'uid': that.uid
                },
                Func); //加载数据
                Func = null;
            }
        },
        
        buildHorses: function() {
            //console.log(this.horsedata);
            var data = this.horsedata;
            
            var html = '';
            for (var i = 0;i < 5;i ++) {
                if (data['mid' + i] != 0) {
                    if (i == 0) {
                        html += '<div class="c_s_i_bj1 hbox hbox' + i + '">';
                        html += '<span class="pic" style="background-position:' + helper.getHorsePostion('big', data['mid' + i].mid) + ';"></span>';
                        html += '<p class="name" style="color:' + helper.getItemColor(data['mid' + i].quality) + '">' + data['mid' + i].name + '</p>';
                        html += '<p class="lv">Lv.' + data['mid' + i].level + '</p>';
                    } else {
                        html += '<div class="c_s_i_bj' + data['mid' + i].quality + ' hbox hbox' + i + '">';
                        html += '<span class="pic" style="background-position:' + helper.getHorsePostion('small', data['mid' + i].mid) + ';"></span>';
                    }
                } else {
                    html += '<div class="c_s_i_bj1 hbox hbox' + i + '">';
                }
                html += '</div>';
            }
            html += '<div class="text_bg"></div>';
            html += '<p class="info_mount_hero">' + lang('mountpop_total', [data.name]) + '</p>';
            html += '<ul class="info_mount_ul">';
            html += '<li>' +  (data.type == 1 ? lang('p_attack') : lang('m_attack')) + ':<b>+' + data.attack + '</b></li>';
            html += '<li>' + lang('p_defense') + ':<b>+' + data.wufang + '</b></li>';
            html += '<li>' + lang('life_num') + ':<b>+' + data.hp + '</b></li>';
            html += '<li>' + lang('m_defense') + ':<b>+' + data.cefang + '</b></li>';
            html += '</ul>';
            
            this.e.find('#infopop_mout').html(html);
            this.e.find('.right').find('#rt').show();
            this.e.find('.right').find('#infopop_rb').attr('class', 'mount');
        },

        playAnim: function(obj, quality) {

            var that = this;

            if (parseInt(quality) == 0) {
                return false;
            }

            that.anim = [];

            // 动画位置数据
            var animPos = [
                [-14, -12],
                [-14, 118],
                [276, -12],
                [276, 118]
            ];

            for (var i = 0; i < 4; i++) {

                that.anim[i] = new movieClipClass({
                    "img": staticUrl + 'img/pop/refine_animate' + quality + '.png',
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

        unbindAll: function () {
            this.e.find('#infopop_hidebtn').unbind('touchend');
            this.e.find('#info_contact_btn').unbind('touchend');
            this.e.find('#info_pk_btn').unbind('touchend');
            this.e.find('#info_chat_btn').unbind('touchend');
            this.e.find('#name').unbind('touchend');
            this.e.find('.left').find('.list').undelegate('.head', 'touchend');
            this.e.find('#infopop_list').undelegate('.infobtn', 'click');
            this.e.find('#info_gem_list').undelegate('li', 'touchend');
            this.e.find('#g_book').undelegate('li .btn', 'touchend');
        },
        
        hide: function(callback) {
            xGame.unlock();
            this.isshow = false;
            this.hideAnim();

            if (this.tabScroller != null) {
                this.tabScroller.destroy();
                this.tabScroller = null;
            }

            if (this.collegeinfoScroller != null) {
                this.collegeinfoScroller.destroy();
                this.collegeinfoScroller = null;
            }
            
            if (this.beautyinfoScroller != null) {
                this.beautyinfoScroller.destroy();
                this.beautyinfoScroller = null;
            }

            if (this.lordtreeScroller != null) {
                this.lordtreeScroller.destroy();
                this.lordtreeScroller = null;
            }

            if (this.myScroller != null) {
                this.myScroller.destroy();
                this.myScroller =null;
            }

            if (this.myScroller != null) {
                this.myScroller.destroy();
                this.myScroller =null;
            }

            if (this.gemScroll) {
                this.gemScroll.destroy();
                this.gemScroll = null;
            }

            this.gid = null;
            this.uid = null;
            this.lastbookuid = null;
            this.lasttalentuid = null;
            this.lastgid = null;
            this.talentdata = null;
            this.souldata = null;
            this.countrycollegedata = null;
            this.lordsouldata = null;
            this.beautydata = null;
            this.lastfunc = null;
            this.privateName = null;
            
            
            this.url = null;
            this.gemdata = null;
            this.tree = null;
            if (this.e) {
                this.e.remove();
                this.e = null;
            }
            if (this.isTimeout) {
                clearTimeout(this.isTimeout);
                this.isTimeout = null;
            }
            xGame.updateCd('collegeinfo', 0);
            
            if (callback) {
                callback();
            }
        },
    };
})();