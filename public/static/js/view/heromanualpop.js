/* 名将图鉴 */
(function() {
    heromanualPopClass = function() {
        this.e;
        this.isshow = false;
        this.myScroll;//左右滑动
        this.starDom;//武将星级dom
        this.collectionDom;//名将成就dom
        this.infoDom;//武将信息dom
        this.collectionScroll;//名将成就滑动
        this.collectionInfo;
        this.perpage;
        this.page;
        this.pages;
        this.downloadPage;

        this.previewData;//预览数据以achievement的id为键
        this.levelupData;//升级数据以achievement的id为键
        this.heroData;//武将信息，以武将id为键
        this.isTimeout = [];

        this.ishappyopen = 1; //是否开启周年庆活动
        this.happycd = 0; //周年庆活动倒计时
    };
    heromanualPopClass.prototype = {
        init: function() {
            var that = this;

            that.perpage = 18;//每次获取两页数据,不会变化的 只能是9的倍数

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.initPage(res);
                        break;
                    case -4:
                        xGame.toast.show(lang('get_chengjiu_info_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('get_tujian_list_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_hero_tmpl_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('lv_not_enough'));
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
                'c': 'general_book',
                'm': 'index',
                'perpage' : that.perpage
            },
            initRender, true);
            initRender = null;

        },

        //设置页面
        initPage: function(res) {
            var that = this;

            that.page = 1;//当前的页码,变量
            that.pages = Math.ceil(res.count / 9);//总页数,定量
            that.downloadPage = that.perpage / 9;//已下载的页码,变量
            this.ishappyopen = res._happy_open; //是否开启周年庆活动
            this.happycd = res._happy_cd;

            that.e = xGame.loadPopTmpl('heromanual');

            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                heromanualpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.collectionDom = that.e.find('#hero_collection');
            that.collectionInfo = that.e.find('#hero_collection_info');
            that.infoDom = that.e.find('#hero_c_info')

            that.buildlist(res);
            that.fillInfo(res.addition);

            /*TODO
              武将详细页面，分页，滑动刷新
            */
            //that.starDom = that.e.find('.star').find('span');
            //that.starDom.width('180px');

            that.bindAll();

            that.e.show();
            $('#worldloading').hide();

        },

        showGem3: function(num){
            this.collectionDom.find('#collection_gem3').html(num);
        },

        showGem2: function(num){
            this.collectionDom.find('#collection_gem2').html(num);
        },

        bindAll: function(){
            var that = this;

            that.e.undelegate('.pop_btn_big', 'touchend').delegate('.pop_btn_big', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.getAchievementList();
            });

            that.e.find('#heromanual_ul').undelegate('.heroitem', 'click').delegate('.heroitem', 'click',
            function() {
                return false;
                if (xGame.checkLock(true)) {
                    return false;
                }

                var data = that.heroData[$(this).attr('hid')];
                var dom = that.infoDom.find('#general_info');
                //console.log(data);
                dom.find('#name').html(data.name);
                dom.find('#wuli').html(data.wuli);
                dom.find('#zili').html(data.zhili);
                dom.find('#tili').html(data.tili);
                dom.find('#bar_hero_star').css({
                    'width': 18 * parseInt(data.star) + 'px'
                });
                dom.find('#skill').html(data.skill.name + ':' + data.skill.description);
                dom.find('#talent').html(data.talent.name + ':' + data.talent.description);

                that.infoDom.css('display','block');

                data = null;
                dom = null;

            });

            that.infoDom.undelegate('#hero_c_info_hide', 'touchend').delegate('#hero_c_info_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.infoDom.css('display','none');
            });

            that.collectionDom.undelegate('#collection_hide', 'touchend').delegate('#collection_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }

                if (that.collectionScroll != null) { //先干掉原本的滑动
                    that.collectionScroll.destroy();
                }

                that.collectionDom.css('display','none');
                that.collectionDom.find('#collection_lvup').css('display','none');
            });

            that.collectionDom.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.levelupConfirm($(this).attr('pid'));
            });

            that.collectionDom.undelegate('.pop_btn_small.preview', 'touchend').delegate('.pop_btn_small.preview', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.fillCollectionInfo($(this).attr('pid'), $(this).attr('ptype'));
            });

            that.collectionDom.undelegate('.pop_btn_small.viewhero', 'touchend').delegate('.pop_btn_small.viewhero', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                var ul = $(this).siblings('ul');

                if (ul.hasClass('show')) {
                    ul.attr('class', 'hide');
                    ul.animate({'height': 0}, 500).hide();
                    $(this).siblings('.btn').hide();
                    $(this).siblings('.p3').show();
                    $(this).parent().animate({'height': '132px'}, 500);
                    $(this).html(lang('champions_view'));
                } else {
                    ul.attr('class', 'show');
                    ul.animate({'height': '118px'}, 500).show();
                    $(this).siblings('.btn').show();
                    $(this).siblings('.p3').hide();
                    $(this).parent().animate({'height': '250px'}, 500);
                    $(this).html(lang('infopop_hide'));
                }
                setTimeout(function () {
                    that.collectionScroll.refresh();
                }, 600);
                ul = null;
                e.preventDefault();
            });

            that.collectionInfo.undelegate('#c_info_hide', 'touchend').delegate('#c_info_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.collectionInfo.css('display','none');
            });
        },

        getMore: function(page){
            var that = this;
            //console.log('调用getMore');
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        //获得的数据可以当几页
                        var getPage = Math.ceil(res.book.length / 9);
                        //重新赋值
                        that.page = res.page;
                        that.downloadPage = that.downloadPage + getPage;

                        var obj = that.e.find('#heromanual_ul');
                        var html = that.getHtml(res);
                        //删除空白页，插入获得的数据
                        obj.find('.page' + res.page).remove();

                        $(html).insertAfter(obj.find('.full').last());

                        var scrollTo;
                        if (that.perpage == 9) {
                            scrollTo = res.page - 1;
                        } else {
                            scrollTo = (that.perpage / 9) * (res.page - 1);
                        }
                        heromanualpop.myScroll.scrollToPage(scrollTo);

                        getPage = obj = html = scrollTo = null;
                        break;
                    case -3:
                        xGame.toast.show(lang('get_tujian_list_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_hero_tmpl_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             loadContents({
                'c': 'general_book',
                'm': 'get_book_list',
                'page': page,
                'perpage' : that.perpage
            },
            Func);
            Func = null;
        },

        //升级提示
        levelupConfirm: function(id) {
            var html = '';
            var data = this.levelupData[id];
            //console.log(data);
            html += '<p class="cp1 sp">' + lang('current') + ':</p>';
            html += '<p class="cp2 sp">' + this.getStr(data.now, 3); + '</p>';
            html += '<p class="cp3 sp">' + lang('next_lv2') + ':</p>';
            html += '<p class="cp4 sp">' + this.getStr(data.next, 3); + '</p>';
            if (this.ishappyopen == 1) {
                html += '<p class="cp6 sp">'+lang('heromanual_limit_msg')+'</p>';
                html += '<p class="cp7 sp">'+lang('heromanual_time_msg')+'<span class="heromanualcd"></span></p>';
                var that = this;
                setTimeout(function(){
                    xGame.updateCd('heromanualLimit', that.happycd);
                }, 100);
            }
            html += '<p class="cp5 sp">' + lang('lvup_cost') + ':<span class="gem' + data.now.paytype + '">' + data.now.number + '</span></p>';

            xGame.confirm.show('', html, 2,
            function() {
                xGame.updateCd('heromanualLimit', 0);
                xGame.confirm.hide();
                heromanualpop.levelUp(id);
            },
            function() {
                xGame.updateCd('heromanualLimit', 0);
                xGame.confirm.hide();
            },lang('level_up'),lang('cancle'));
            html = null;
        },

        //升级接口
        levelUp: function(id){
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        that.lvupAnim(id, res);
                        that.fillInfo(res.addition);
                        break;
                    case -7:
                        xGame.toast.show(lang('level_up_fail'));
                        break;
                    case -6:
                        xGame.toast.show(lang('deduct_gem_fail'));
                        break;
                    case -5:
                        xGame.toast.show(lang('jade_not_enough'));
                        break;
                    case -4:
                        xGame.toast.show(lang('get_lvup_cost_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('get_chengjiu_plus_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_own_chengjiu_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             loadContents({
                'c': 'general_book',
                'm': 'levelup',
                'id': id,
            },
            Func);
            Func = null;
        },

        getAchievementList: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        that.fillCollection(res.achievement, res.jade);
                        break;
                    case -5:
                        xGame.toast.show(lang('get_gem_num_fail'));
                        break;
                    case -4:
                        xGame.toast.show(lang('get_chengjiu_plus_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('get_chengjiu_list_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_hero_tmpl_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'general_book',
                'm': 'get_achievement_list'
            },
            Func);
            Func = null;
        },

        fillCollectionInfo: function(id, paytype){
            var data = this.previewData[id];
            //console.log(data);
            this.collectionInfo.find('span').html(data.name + ':').css('color',helper.getItemColor(data.quality));
            var html = '';
            for (var i = 0;i < data.length;i ++) {
                html += '<li style="color:' + helper.getItemColor(data[i].quality) + ';">' + helper.getQualityName(data[i].quality) + ':' + this.getStr(data[i], 1) + '&nbsp;&nbsp;' + lang('all_of_hero') + '</li>';
            }
            this.collectionInfo.find('ul').html(html);
            this.collectionInfo.find('#jade').attr('class', 'type' + paytype);
            this.collectionInfo.css('display','block');
            html = null;
            data = null;
        },

        //共用的获得字符串方法
        getStr: function(data, type, isZero){
            var str = '';
            for (var i = 1;i < 4;i ++) {
                if (data['addtype' + i] != 0) {
                    if (type == 1) {
                        str += '&nbsp;&nbsp;' + helper.getBookTypeName(data['addtype' + i]) + this.getNum(data['addvalue' + i], data['addtype' + i], isZero);
                    } else if (type == 2) {
                        str += helper.getBookTypeName(data['addtype' + i]) + this.getNum(data['addvalue' + i], data['addtype' + i], isZero, type) + '&nbsp;&nbsp;&nbsp;&nbsp;';
                    } else if (type == 3) {
                        str += helper.getBookTypeName(data['addtype' + i]) + this.getNum(data['addvalue' + i], data['addtype' + i], isZero) + '&nbsp;&nbsp;';
                    }
                }
            }
            return str;
        },

        //通过数字，类型，是否是0,类型从helper里面取,主要是0%的时候要处理，其他不处理
        getNum: function(num, type ,isZero, channel){
            type = parseInt(type);
            if (isZero != undefined) {
                num = 0;
                if (type == 9 || type == 10 || type == 11 || type == 13 || type == 14) {
                    num = num + '%';
                }
                num = '+' + num;
                return num;
            } else {
                num = '+' + num;
                if (channel == 2) {
                    num = '<em>' + num + '</em>';
                }
                return num;
            }
        },

        fillCollection: function(list, jade){
            var html = '';
            var html1 = '';
            var nameStr = '';
            this.previewData = {};
            this.levelupData = {};
            for (var i = 0;i < list.length;i ++) {
                nameStr = '';
                html1 = '';
                html += '<li id="hc_' + list[i].id + '">';
                html += '<button class="btn pop_btn_small preview" pid="' + list[i].id + '" ptype="' + list[i].paytype + '">' + lang('preview') + '</button>';
                html += '<button class="btn pop_btn_small viewhero">' + lang('champions_view') + '</button>';

                //保存预览数据
                this.previewData[list[i].id] = list[i].preview;
                this.previewData[list[i].id]['name'] = list[i].name;
                this.previewData[list[i].id]['quality'] = list[i].quality;

                html += '<ul>';
                for (var j = 1;j < 6;j ++) {
                    if (list[i]['gid' + j] != 0) {
                        html += '<li class="heroitem">';
                        html += '<div class="itempic" style="background:url(' + xGame.avatar(list[i]['gid' + j].image) + ') center center no-repeat;"></div>';
                        if (list[i]['gid' + j].isown == 1) {
                            html += '<p class="name n_isown">' + list[i]['gid' + j].name + '</p>';
                            html += '<span class="isown"></span>';
                            html1 += '<em class="name isown">' + list[i]['gid' + j].name + '</em>';
                        } else {
                            html += '<p class="name">' + list[i]['gid' + j].name + '</p>';
                            html += '<span class="hero_mask"></span>';
                            html1 += '<em class="name">' + list[i]['gid' + j].name + '</em>';
                        }
                        nameStr += list[i]['gid' + j].name + ' ';
                        html += '</li>';
                    }
                }
                html += '</ul>';
                if (list[i].complete == 1) {
                    html += '<span class="cname" style="color:' + helper.getItemColor(list[i].quality) + ';">' + list[i].name + '</span>';
                    html += '<p class="p1">' + lang('current_lv') + '<em style="color:' + helper.getItemColor(list[i].quality) + ';">' + helper.getQualityName(list[i].quality) + '</em></p>';
                    html += '<p class="p2">' + this.getStr(list[i].addition, 2) + '</p>';

                    if (list[i].ismax == 1) {
                        html += '<button class="btn pop_btn_middle_disable">' + lang('is_full_s') + '</button>';
                    } else {
                        html += '<button class="btn pop_btn_middle" pid="' + list[i].id + '">' + lang('click_to_lvup') + '</button>';
                        this.levelupData[list[i].id] = list[i].levelup;
                    }
                } else {
                    html += '<span class="cname" style="color:gray;">' + list[i].name + '</span>';
                    html += '<p class="p1">' + lang('current_lv') + '<font style="color:gray;">' + lang('not_reached') + '</font></p>';
                    html += '<p class="p2">' + this.getStr(list[i].addition, 2, true) + '</p>';
                    html += '<button class="btn pop_btn_middle_disable">' + lang('click_to_lvup') + '</button>';
                }
                html += '<p class="p3">' + lang('heromanualpop_needhero') + '<span class="need_hero">' + html1 + '</span></p>';
                html += '</li>';
            }

            this.collectionDom.find('#collection_list').html(html);

            this.collectionDom.css('display','block');

            this.collectionScroll = new iScroll('collection_scroller', {
                bounce: false,
                hScrollbar: false,
                vScrollbar: false
            });

            this.collectionScroll.refresh();

            this.showGem2(jade[2]);
            this.showGem3(jade[3]);
        },

        buildlist: function(res) {
            var that = this;

            that.heroData = {};

            var html = that.getHtml(res);

            //计算还需要几个空白页
            var n = Math.ceil((res.count - that.perpage) / 9);
            //新加入的方法，补空白页数
            for (var i = 1; i <= n;i ++) {
                html += '<li class="cs_item hero_list page' + (1 + Math.ceil(i / (that.perpage / 9))) + '">' + helper.str_repeat('<div class="heroitem"><div class="itempic">' + lang('loading') + '</div><p class="plus">&nbsp;</p></div>', 9) + '</li>';
            }

            that.e.find('#heromanual_ul').html(html);

            if (that.myScroll != null) { //先干掉原本的滑动
                that.myScroll.scrollTo(0, 0);
                that.myScroll.destroy();
                delete that.myScroll;
            }
            /* 创建旋转门 domid, 单页宽度 */
            that.myScroll = xGame.carousel('heromanual_carousel', 450, that.pages, function() {
                //当前页码
                if (heromanualpop.myScroll.currPageX + 1 <= heromanualpop.downloadPage) {
                    //console.log('下载过了，不需要获取');
                    return false;
                } else {
                    heromanualpop.getMore(heromanualpop.page + 1);
                }
            });
        },

        getHtml: function(res){
            var html = '<li class="cs_item hero_list full">';
            var n = 0;

            for (var i in res.book) {
                if (n > 0 && n % 9 == 0) { //下一页了
                    html += '</li><li class="cs_item hero_list full">';
                }
                var item = res.book[i].info;
                html += '<div hid="' + item.id + '" class="heroitem">';
                html += '<div class="itempic" style="background:url(' + xGame.avatar(item.image) + ') center center no-repeat;' + '"></div>';
                if (res.book[i].isown == 1) {
                    html += '<p class="name n_isown">' + item.name + '</p>';
                    html += '<span class="isown"></span>';
                } else {
                    html += '<p class="name">' + item.name + '</p>';
                    html += '<span class="hero_mask"></span>';
                }
                html += '<p class="plus">' + helper.getBookTypeName(res.book[i].addtype) + '+' + res.book[i].addvalue + '</p>';
                html += '</div>';

                n++;
                this.heroData[item.id] = item;
            }
            /* 补空格 */
            var repeat_s = '<div class="heroitem"><div class="itempic"></div><p class="plus">&nbsp;</p></div>';
            if (n == 0) { //神马也没有
                html += helper.str_repeat(repeat_s, 9);
            } else if (n % 9 == 0) {
                html += '';
            } else {
                html += helper.str_repeat(repeat_s, 9 - n % 9);
            }
            html += '</li>';
            return html;
        },

        fillInfo: function(data) {

            var html = '';
            html += '<li>' + lang('wuli') + ':<em>' + (data.wuli != undefined ? '+' + data.wuli : '<b>+0</b>') + '</em></li>';
            html += '<li>' + lang('zhili') + ':<em>' + (data.zhili != undefined ? '+' + data.zhili : '<b>+0</b>') + '</em></li>';
            html += '<li>' + lang('tili') + ':<em>' + (data.tili != undefined ? '+' + data.tili : '<b>+0</b>') + '</em></li>';
            html += '<li>' + lang('miss') + ':<em>' + (data.dodge != undefined ? '+' + data.dodge : '<b>+0%</b>') + '</em></li>';
            html += '<li>' + lang('crit_rate') + ':<em>' + (data.crit != undefined ? '+' + data.crit : '<b>+0%</b>') + '</em></li>';
            html += '<li>' + lang('shipo') + ':<em>' + (data.penetrate != undefined ? '+' + data.penetrate : '<b>+0%</b>') + '</em></li>';
            html += '<li>' + lang('mingzhong') + ':<em>' + (data.mingzhong != undefined ? '+' + data.mingzhong : '<b>+0%</b>') + '</em></li>';
            this.e.find('#heromanual_ul1').html(html);

            html = '';
            html += '<li>' + lang('p_attack') + ':<em>' + (data.attack != undefined ? '+' + data.attack : '<b>+0</b>') + '</em></li>';
            html += '<li>' + lang('p_defense') + ':<em>' + (data.defense != undefined ? '+' + data.defense : '<b>+0</b>') + '</em></li>';
            html += '<li>' + lang('m_attack') + ':<em>' + (data.pattack != undefined ? '+' + data.pattack : '<b>+0</b>') + '</em></li>';
            html += '<li>' + lang('m_defense') + ':<em>' + (data.pdefense != undefined ? '+' + data.pdefense : '<b>+0</b>') + '</em></li>';
            html += '<li>' + lang('life_num') + ':<em>' + (data.blood != undefined ? '+' + data.blood : '<b>+0</b>') + '</em></li>';
            html += '<li>' + lang('chushishiqi') + ':<em>' + (data.power != undefined ? '+' + data.power : '<b>+0</b>') + '</em></li>';
            html += '<li>' + lang('jingzhun') + ':<em>' + (data.jingzhun != undefined ? '+' + data.jingzhun : '<b>+0%</b>') + '</em></li>';
            this.e.find('#heromanual_ul2').html(html);
            html = null;
        },

        lvupAnim: function(id, res){
            //动画开始就锁上
            xGame.islock = true;
            var obj = $('#hc_' + id);
            obj.find('ul').find('li').append('<span class="hero_mask all"></span>');
            var time = obj.find('ul').find('li').length;
            var i = 0;
            var that = this;
            var off = function(){
                obj.find('ul').find('li').eq(i).find('span.all').remove();
                i += 1;
                if (i < time) {
                    that.isTimeout.push(setTimeout(function(){
                        off();
                    },200));
                } else {
                    that.isTimeout.push(setTimeout(function(){
                        that.lightAnim(id, res);
                        obj = that = null;
                        time = i = null;
                    },200));
                    return false;
                }
            };
            this.isTimeout.push(setTimeout(function(){
                off();
            },500));
        },

        lightAnim: function(id, res){
            var that = this;
            var obj = $('#collection_bg');

            var callback = function(res){
                if (res.jade[2] != undefined) {
                    that.showGem2(res.jade[2]);
                } else if (res.jade[3] != undefined) {
                    that.showGem3(res.jade[3]);
                }

                var obj2 = $('#hc_' + id);
                if (res.ismax == 1) {
                    obj2.find('.pop_btn_middle').attr('class','btn pop_btn_middle_disable').html(lang('is_full_s'));
                }
                obj2.find('.cname').html(res.levelup.name).css('color',helper.getItemColor(res.levelup.quality));
                obj2.find('.p1').find('em').html(helper.getQualityName(res.levelup.quality)).css('color',helper.getItemColor(res.levelup.quality));
                obj2.find('.p2').html(that.getStr(res.levelup, 2));
                obj2 = null;

                that.levelupData[id]['now'] = res.levelup;
                that.levelupData[id]['next'] = res.next;
                //解锁
                xGame.unlock();
            }

            that.movieclip1 = new movieClipClass({
                "img": staticUrl + 'img/pop/heromanualpop_light.png',
                "width": 380,
                "height": 250,
                "x": 195,
                "y": 145,
                "loop": false,
                'zindex': '1',
                "frames": [{
                    rect: [0, 0],
                    interval: 100
                },
                {
                    rect: [380, 0],
                    interval: 100
                },
                {
                    rect: [760, 0],
                    interval: 100
                },
                {
                    rect: [0, 250],
                    interval: 100
                },
                {
                    rect: [380, 250],
                    interval: 100
                },
                {
                    rect: [760, 250],
                    interval: 100
                }],
                'callback' : callback(res),
                'endDestory': true
            });
            that.movieclip1.appendTo(obj);
            that.movieclip1.play();
            obj.find('#collection_lvup').css('display','block').unbind('webkitAnimationEnd').one('webkitAnimationEnd',function(){
                $(this).css('display','none');
            })
            obj = that = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.undelegate('.pop_btn_big', 'touchend');
                this.e.find('#heromanual_ul').undelegate('.heroitem', 'click');
                this.e.remove();
                this.e = null;
            }
            if (this.infoDom) {
                this.infoDom.undelegate('#hero_c_info_hide', 'touchend');
                this.infoDom.remove();
                this.infoDom = null;
            }
            if (this.collectionDom) {
                this.collectionDom.undelegate('#collection_hide', 'touchend');
                this.collectionDom.undelegate('.pop_btn_middle', 'touchend');
                this.collectionDom.undelegate('.pop_btn_small', 'touchend');
                this.collectionInfo.undelegate('#c_info_hide', 'touchend');
                this.collectionDom.remove();
                this.collectionDom = null;
            }
            if (this.collectionInfo) {
                this.collectionInfo.undelegate('#c_info_hide', 'touchend');
                this.collectionInfo.remove();
                this.collectionInfo = null;
            }
        },

        hide: function() {

            xGame.unlock();

            this.isshow = false;

            this.starDom = null;
            this.perpage = null;
            this.page = null;
            this.pages = null;
            this.downloadPage = null;

            this.previewData = null;
            this.levelupData = null;
            this.heroData = null;

            if (this.myScroll != null) { //先干掉原本的滑动
                this.myScroll.destroy();
                delete this.myScroll;
            }
            if (this.collectionScroll != null) { //先干掉原本的滑动
                this.collectionScroll.destroy();
                delete this.collectionScroll;
            }

            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            this.unbindAll();
        },
    };
})();