/* 坐骑 */
(function() {
    mountPopClass = function() {
        this.e;
        this.isshow = false;
        this.type;//显示坐骑还是显示坐骑升级
        this.mainDom;//主容器
        
        this.myScroll;
        this.moveDom;
        this.boxPos;//背包里的位置坐标
        this.equipPos;//装备坐标
        this.lvupPos;//升级坐标
        this.zIndex;//当前的zindex
        this.touchPosData;//当前选中对象的pos对象
        this.nearlyPosData;//最近的对象的pos对象
        this.distance;//边界检查依据
        
        this.currentHorseData;//当前的马匹数据
        this.horsesData;//每一页的马匹数据
        this.heroData;//当前英雄的数据
        this.page;//当前页码
        this.pages;//总页数
        this.maxWidth;//最长的经验条长度
        this.minWidth;//最小经验条长度
        this.eatenData;//升级需要的数据
        this.lvupHorseData;//要升级的这匹马的数据
        this.tempData;//放在升级位置上的位置数据，以马匹id为键
        this.totalExp;//装备的马的总经验
        this.mc;
        this.timer;
    };
    mountPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case - 1 : 
                        xGame.toast.show(lang('mountpop_get_hero_fail'));
                        $('#worldloading').hide();
                        break;
                    case 2:
                        xGame.toast.show(lang('studfarmpop_lv_too_low'));
                        $('#worldloading').hide();
                        break;
                    case 1:
                        that.initPage(res);
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        $('#worldloading').hide();
                        break;
                    }
            };
            loadContents({
                'c': 'mount',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;

        },
        
        initPage: function(res) {
            var that = this;
            
            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('mount');
            that.e.find('#mountpop_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                mountpop.hide();
                e.preventDefault();
            });
            
            that.mainDom = that.e.find('#mountpop_wrap');
            that.moveDom = that.e.find('#mount_move_dom');
            
            that.type = 1;//默认显示坐骑界面
            that.mainDom.attr('class', 'content show' + that.type);
            
            that.horsesData = res.mlist;
            that.tempData = {};
            
            that.zIndex = 0;
            that.mid = 0;
            that.maxWidth = 304;
            that.minWidth = 30;
            
            that.distance = that.getDistance(100, 100);
            that.page = res.page;
            that.pages = res.allpage;
            
            that.initPosData();
            //先更新英雄数据
            that.fillHeroList(res.general);
            that.fillGInfo(res.ginfo);
            //再更新马匹数据
            that.fillHorseInfo(res.minfo);
            that.fillPackage(res.mlist);
            
            that.bindAll();
            
            that.e.show();
            $('#worldloading').hide();
        },
        
        initPosData: function() {
            //设置背包坐标
            var w = 120;
            var h = 142;
            this.boxPos = {};
            //key就是pos属性
            for(var i = 1;i < 9;i ++) {
                this.boxPos[i] = {};
                this.boxPos[i].left = (i - 1) % 4 * w + 10;
                this.boxPos[i].top = (Math.ceil(i / 4) - 1) * h + 10;
                this.boxPos[i].style = 'left:' + this.boxPos[i].left + 'px;top:' + this.boxPos[i].top + 'px;';
            }
            
            this.equipPos = {
                0: {
                    'left': -300,
                    'top': -10,
                    'style': 'left:-300px;top:-10px;',
                    'pos': 0
                },
                1: {
                    'left': -415,
                    'top': -18,
                    'style': 'left:-415px;top:-18px;',
                    'pos': 1
                },
                2: {
                    'left': -415,
                    'top': 107,
                    'style': 'left:-415px;top:107px;',
                    'pos': 2
                },
                3: {
                    'left': -118,
                    'top': -18,
                    'style': 'left:-118px;top:-18px;',
                    'pos': 3
                },
                4: {
                    'left': -118,
                    'top': 107,
                    'style': 'left:-118px;top:107px;',
                    'pos': 4
                }
            };
            
            this.lvupPos = {
                1: {
                    'left': -414,
                    'top': -135,
                    'style': 'left:-414px;top:-135px;',
                    'pos': 1
                },
                2: {
                    'left': -414,
                    'top': -35,
                    'style': 'left:-414px;top:-35px;',
                    'pos': 2
                },
                3: {
                    'left': -414,
                    'top': 65,
                    'style': 'left:-414px;top:65px;',
                    'pos': 3
                },
                4: {
                    'left': -119,
                    'top': -135,
                    'style': 'left:-119px;top:-135px;',
                    'pos': 4
                },
                5: {
                    'left': -119,
                    'top': -35,
                    'style': 'left:-119px;top:-35px;',
                    'pos': 5
                },
                6: {
                    'left': -119,
                    'top': 65,
                    'style': 'left:-119px;top:65px;',
                    'pos': 6
                }
            }
        },
        
        fillHeroList: function(list) {
            var html = '';
            var n = 0;
            for (var i = 1;i < 9;i ++) {
                if (list[i] != undefined) {
                    html += '<li class="c_i_bj' + list[i].quality + ' heroitem' + (i == 1 ? ' selected': '') + '" heroid="' + list[i].id + '">';
                    html += '<span class="avatar" style="background:url(' + xGame.avatar(list[i].image) + ') center center no-repeat;' + '"></span>';
                    html += '<p class="name">' + list[i].name + '</p></li>';
                    n += 1;
                }
            }
            this.mainDom.find('#mhorse_list_scroller').html(html);
            html = null;
            if (n > 3) {
                this.myScroll = xGame.scroller('mhorse_scroller', n * 120);
            }
        },
        
        fillGInfo: function(data) {
            var dom = this.mainDom.find('#mhorse_lbottom');
            dom.find('#mhorse_total').html(lang('mountpop_total', [data.name]));
            dom.find('ul').html(this.getPluslHtml(data));
            dom = null;
            this.heroData = data;
        },
        
        getPluslHtml: function(data) {
            var html = '';
            html += '<li>' +  (data.type == 1 ? lang('p_attack') : lang('m_attack')) + ':<b>+' + data.attack + '</b></li>';
            html += '<li>' + lang('p_defense') + ':<b>+' + data.wufang + '</b></li>';
            html += '<li>' + lang('life_num') + ':<b>+' + data.hp + '</b></li>';
            html += '<li>' + lang('m_defense') + ':<b>+' + data.cefang + '</b></li>';
            return html;
        },
        
        //填充右上角马匹数据
        fillHorseInfo: function(data) {
            var dom = this.mainDom.find('#horse_info_dom');
            var appendInfoDom = dom.find('#horse_append_info');
            if (data.length == 0) {
                dom.find('#mhorse_equi').attr('class', 'c_i_bj1');
                dom.find('.pic').css('background-position', '100px 100px');
                dom.find('#mhorse_name').html('');
                appendInfoDom.html('');
                dom.find('#mhorse_lv').html('');
                dom.find('ul').html(this.getHorseHtml(data));
                dom.attr('class', 'horse_info_dom nodata');
                this.currentHorseData = {
                    'id': 0
                };
            } else {
                dom.find('#mhorse_equi').attr('class', 'c_i_bj' + data.quality);
                dom.find('.pic').css('background-position', helper.getHorsePostion('small', data.mid));
                dom.find('#mhorse_name').html(data.name).css('color', helper.getItemColor(data.quality));
                dom.find('#mhorse_lv').html(lang('level') + ':<b>Lv.' + data.level + '</b>');
                dom.find('ul').html(this.getHorseHtml(data));

                if (parseInt(data.type) == 1 || parseInt(data.is_full) == 1) {
                    dom.find('#mlvup_btn').hide();

                    if (parseInt(data.type) == 1 && parseInt(data.chip) > 0) {
                        appendInfoDom.html(data.full_num);
                    } else if (parseInt(data.is_full) == 1) {
                        appendInfoDom.html(lang('full_lv'));
                    } else {
                        appendInfoDom.html('');
                    }
                } else {
                    dom.find('#mlvup_btn').show();
                    appendInfoDom.html('');
                }

                if (parseInt(data.gid) > 0) {
                    dom.attr('class', 'horse_info_dom isequiped');
                } else {
                    dom.attr('class', 'horse_info_dom');
                }
                this.currentHorseData = data;
                //todo 已满级需不需要处理
            }
            dom = null;
        },
        
        getHorseHtml: function(data) {
            var html = '';
            if (data.length != 0) {
                html += '<li>' + lang('p_attack') + ':<b>' + data.attack + '</b></li>';
                html += '<li>' + lang('m_attack') + ':<b>' + data.attack + '</b></li>';
                html += '<li>' + lang('p_defense') + ':<b>' + data.wufang + '</b></li>';
                html += '<li>' + lang('m_defense') + ':<b>' + data.cefang + '</b></li>';
                html += '<li>' + lang('life_num') + ':<b>' + data.hp + '</b></li>';
                if(data.special_type != 'undefined' && data.special_type == 1){    //坐骑添加新属性
                   html += '<li>' + lang('attack_force') + ':<b>+' + data.special_value + '%</b></li>';  
                }else if(data.special_type != 'undefined' && data.special_type == 2){
                   html += '<li>' + lang('defense_force') + ':<b>+' + data.special_value + '%</b></li>';  
                }
            }
            return html;
        },
        
        fillPackage: function(data) {
            this.mainDom.find('#horse_box_dom').find('ul').html(this.getPackageHtml(data));
            this.fillHorseList(data);
            this.showPageBtn();
        },
        
        showPageBtn: function() {
            if (this.page == 1 && this.page != this.pages) {
                this.mainDom.find('#mhorse_pages').attr('class', 'begin');
            } else if (this.page == this.pages && this.pages != 1) {
                this.mainDom.find('#mhorse_pages').attr('class', 'end');
            } else if (this.pages == 1){
                this.mainDom.find('#mhorse_pages').attr('class', 'onlyone');
            } else {
                this.mainDom.find('#mhorse_pages').removeClass();
            }
        },
        
        fillHorseList: function(data) {
            var html = '';
            var pos = 0;
            var cn = '';
            var temp = [];
            if (this.type == 1) {
                html += this.getEquipHorse();
            } else {
                temp = this.moveDom.find('.eaten');
                if (temp.length > 0) {
                    temp.addClass('temp');
                }
                //console.log(temp)
            }
            
            for (var i = 0,len = data.length;i < len;i ++) {
                pos += 1;
                //当在坐骑升级界面并且不是temp数据的时候，生成dom
                if (this.type == 1 || (this.type == 2 && this.tempData[data[i].id] == undefined)) {
                    
                    if (parseInt(data[i].chip) > 0) {
                        cn = 'moveitem  unequip islock';
                    } else {
                        cn = 'moveitem  unequip';
                    }

                    html += '<div id="horse' + data[i].id + '" hid="' + data[i].id + '" index="' + i + '" pos="' + pos + '" class="' + cn + '" style="z-index:' + (this.zIndex ++) + ';' + this.boxPos[pos].style + '">';
                    html += '<div class="pic" style="background-position:' + helper.getHorsePostion('small', data[i].mid) + ';"></div>';
                    //html += '<span>' + lang('can_not_equip') + '</span>';

                    if (parseInt(data[i].chip) > 0) {
                        html += '<em class="part_num">' + data[i].full_num + '</em>';
                        html += '<em class="itempart"></em>';
                    }

                    html += '<p class="name" style="color:' + helper.getItemColor(data[i].quality) + '">' + data[i].name + '</p>';
                    html += '</div>';
                } else if (this.tempData[data[i].id] != undefined) {//如果有，重新赋值
                    this.tempData[data[i].id] = data[i];
                }
            }
            
            this.moveDom.html(html);
            if (temp.length > 0) {
                this.moveDom.append(temp);
            }
            html = null;
            pos = null;
            cn = null;
            temp = null;
        },
        
        //设置马匹的背景顺便返回moveDom里的html
        getEquipHorse: function() {
            var dom = this.mainDom.find('#mhorse_five');
            var fiveHtml = '';
            var html = '';
            var data;
            for (var i = 0;i < 5;i ++) {
                data = this.heroData['mid' + i];
                if (i == 0) {
                    if (data == 0) {
                        fiveHtml += '<div id="mhorse_box0" class="first"></div>';
                    } else {
                        fiveHtml += '<div id="mhorse_box0" class="first' + (data.id == this.currentHorseData.id ? ' selected' : '') + '">';
                        fiveHtml +='<div class="pic" style="background-position:' + helper.getHorsePostion('big', data.mid) + ';"></div>';
                        fiveHtml += '<p class="name" style="color:' + helper.getItemColor(data.quality) + '">' + data.name + '</p>';
                        fiveHtml += '<p class="lv">Lv.' + data.level + '</p>';
                        fiveHtml += '</div>';
                        
                        html += '<div id="horse' + data.id + '" mid="' + data.mid + '" hid="' + data.id + '" pos="' + i + '" class="moveitem isequiped big" style="' + this.equipPos[i].style + 'z-index:' + (this.zIndex++) + ';"></div>';
                    }
                } else {
                    if (data == 0) {
                        fiveHtml += '<div id="mhorse_box' + i + '" class="second c_s_i_bj1"></div>';
                    } else {
                        html += '<div id="horse' + data.id + '" hid="' + data.id + '" pos="' + i + '" class="moveitem isequiped" style="' + this.equipPos[i].style + 'background-position:' + helper.getHorsePostion('small', data.mid) + ';z-index:' + (this.zIndex++) + ';"></div>';
                        fiveHtml += '<div id="mhorse_box' + i + '" class="second c_s_i_bj' + data.quality + (data.id == this.currentHorseData.id ? ' selected' : '') + '"></div>';
                    }
                }
            }
            dom.html(fiveHtml);
            fiveHtml = null;
            dom = null;
            return html;
        },
        
        getPackageHtml: function(data) {
            var html = '';
            for (var i = 0;i < 8;i ++) {
                if (data[i] != undefined) {
                    //是当前选中的马匹
                    if (this.currentHorseData.id == data[i].id) {
                        html += '<li class="selected itembg c_i_bj' + data[i].quality + '" id="hbox' + data[i].id + '"></li>';
                    } else {
                        html += '<li class="itembg c_i_bj' + data[i].quality + '" id="hbox' + data[i].id + '"></li>';
                    }
                } else {
                    html += '<li class="itembg c_i_bj1"></li>';
                }                
            }
            return html;
        },
        
        getHeroInfo: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) { 
                    case - 1 : xGame.toast.show(lang('mountpop_get_hero_fail'));
                        break;
                    case 2:
                        xGame.toast.show(lang('studfarmpop_lv_too_low'));
                        break;
                    case 1:
                        that.fillGInfo(res.info);
                        that.mainDom.find('#mhorse_ltop').find('.selected').removeClass('selected');
                        obj.addClass('selected');
                        
                        that.fillHorseInfo(res.minfo);
                        that.fillPackage(that.horsesData);
                        
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'mount',
                'm': 'get_info_by_id',
                'gid': obj.attr('heroid'),
                'page': that.page
            },
            Func);
            Func = null;
        },
        
        //装备方法
        equipFunc: function(site, obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                if (res.status != 1) {
                    that.errorFunc(obj);
                }
                switch (parseInt(res.status)) { 
                    case - 1 : 
                        xGame.toast.show(lang('canshu_lost'));
                        break;
                    case - 2 : 
                        xGame.toast.show(lang('get_hero_info_fail'));
                        break;
                    case - 3 : 
                        xGame.toast.show(lang('studfarmpop_get_horse_fail'));
                        break;
                    case - 4 : 
                        xGame.toast.show(lang('studfarmpop_get_horse_fail'));
                        break;
                    case - 5 : 
                        xGame.toast.show(lang('mountpop_job_not_match'));
                        break;
                    case - 6 : 
                        xGame.toast.show(lang('mountpop_horse_used'));
                        break;
                    case - 8 : 
                        xGame.toast.show(lang('mountpop_only_one'));
                        break;
                    case 2:
                        xGame.toast.show(lang('studfarmpop_lv_too_low'));
                        break;
                    case 1:
                        that.page = res.page;
                        that.pages = res.allpage;
                        that.horsesData = res.mlist;
                        that.fillGInfo(res.info);
                        that.fillHorseInfo(res.minfo);
                        that.fillPackage(res.mlist);
                        that.removeSign(obj);
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'mount',
                'm': 'fit_on',
                'gid': that.heroData.id,
                'mid': that.currentHorseData.id,
                'site': site,
                'page': that.page
            },
            Func);
            Func = null;
        },
        
        //卸载方法
        unequipFunc: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                if (res.status != 1) {
                    that.errorFunc(obj);
                }
                switch (parseInt(res.status)) { 
                    case - 1 : 
                        xGame.toast.show(lang('studfarmpop_get_horse_fail'));
                        break;
                    case 2:
                        xGame.toast.show(lang('studfarmpop_lv_too_low'));
                        break;
                    case 1:
                        that.page = res.page;
                        that.pages = res.allpage;
                        that.horsesData = res.mlist;
                        that.fillGInfo(res.info);
                        that.fillHorseInfo(res.minfo);
                        that.fillPackage(res.mlist);
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'mount',
                'm': 'take_down',
                'mid': that.currentHorseData.id,
                'page': that.page
            },
            Func);
            Func = null;
        },
        
        //翻页方法
        flipPageFunc: function(page, mid) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) { 
                    case - 1 : 
                        xGame.toast.show(lang('studfarmpop_get_horse_fail'));
                        break;
                    case 2:
                        xGame.toast.show(lang('studfarmpop_lv_too_low'));
                        break;
                    case 1:
                        that.page = res.page;
                        that.pages = res.allpage;
                        that.horsesData = res.list;
                        
                        that.fillHorseInfo(res.info);
                        that.fillPackage(res.list);
                        //如果是坐骑界面并且第一匹马在升级位置上，清除背包里的选中状态,给升级位置加上
                        if (that.type == 2) {
                            that.mainDom.find('#mhorse_seven').find('.selected').removeClass('selected');
                            if (res.list[0] != undefined && that.tempData[res.list[0].id] != undefined) {
                                that.mainDom.find('#horse_box_dom').find('.selected').removeClass('selected');
                                var site = that.mainDom.find('#horse' + res.list[0].id).attr('site');
                                that.mainDom.find('#mhorse_seven').find('#mlvup_box' + site).addClass('selected');
                            }
                        }
                        
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            if (mid == undefined) {
                loadContents({
                    'c': 'mount',
                    'm': 'get_mount_by_page',
                    'page': page
                },
                Func);
            } else {
                loadContents({
                    'c': 'mount',
                    'm': 'get_mount_by_page',
                    'page': page,
                    'mid': mid
                },
                Func);
            }
            Func = null;
        },
        
        //坐骑升级方法,用逗号隔开
        lvupFunc: function(str) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                if (res.status != 1) {
                    xGame.unlock();
                }
                switch (parseInt(res.status)) { 
                    case - 1 : 
                        xGame.toast.show(lang('mountpop_no_horse'));
                        break;
                    case - 2 : 
                        xGame.toast.show(lang('mountpop_eaten_horse_fail'));
                        break;
                    case - 3 : 
                        xGame.toast.show(lang('mountpop_main_horse_fail'));
                        break;
                    case - 4 : 
                        xGame.toast.show(lang('mountpop_full_lv'));
                        break;
                    case - 5 : 
                        xGame.toast.show(lang('mountpop_lvup_fail'));
                        break;
                    case 2:
                        xGame.toast.show(lang('studfarmpop_lv_too_low'));
                        break;
                    case 1:
                        that.mainDom.find('#mlvup_action').attr('class', 'pop_btn_big_disable btn');
                        that.initLvupData();
                        
                        that.page = res.page;
                        that.pages = res.allpage;
                        that.horsesData = res.mlist;
                        that.fillHorseInfo(res.info);
                        that.fillPackage(res.mlist);
                        
                        that.fillBar(res.info);
                        /*
                            left: -266px;top: -29px;
                            参数，以前的等级，升级后的等级，百分比,是否满级,1为满级
                            debug:
                            
                            mountpop.initLvupData()
                            mountpop.moveDom.find('.eaten').addClass('ismoving').animate({
                                'left': '-266px',
                                'top': '-29px',
                            },
                            200, 'linear',function() {
                                mountpop.changBar(1, 3,  parseFloat(parseInt(100) / parseInt(300)).toFixed(2), false);
                                //移到位置后删除
                                mountpop.moveDom.find('.eaten').remove();
                                //抖动一下马匹
                                mountpop.mainDom.find('#mlvup_box0').addClass('tada');
                            });
                            
                            
                        */
                        
                        var oldLv = parseInt(that.lvupHorseData.level);
                        res.info.level = parseInt(res.info.level);
                        that.buildSeven(res.info, oldLv);//因为这里重新生成了底框，所以没必要清除class了
                        that.lvupHorseData = res.info;
                        that.moveDom.find('.eaten').addClass('ismoving');
                        helper.animate(that.moveDom.find('.eaten'), {
                            'left': '-266px',
                            'top': '-29px',
                        },
                        200, 'Linear',function() {
                            //移到位置后删除
                            that.moveDom.find('.eaten').remove();
                            that.changBar(oldLv, res.info.level,  parseFloat(parseInt(res.info.exp) / parseInt(res.info.levelup_exp)).toFixed(2), res.info.is_full);
                            //没有升级的话抖动一下马匹
                            if (res.info.level == oldLv) {
                                that.mainDom.find('#mlvup_box0').addClass('tada');
                            }
                        });

                        that.timer = setTimeout(function () {
                            that.levelupFun(oldLv, res);
                        }, 300);
                        
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'mount',
                'm': 'levelup',
                'id': that.lvupHorseData.id,
                'list': str
            },
            Func);
        },

        levelupFun: function (oldLv, res) {

            var dom = this.moveDom.find('.eaten');

            if (dom.length > 0) {
                this.moveDom.find('.eaten').remove();
                this.changBar(oldLv, res.info.level,  parseFloat(parseInt(res.info.exp) / parseInt(res.info.levelup_exp)).toFixed(2), res.info.is_full);
                //没有升级的话抖动一下马匹
                if (res.info.level == oldLv) {
                    this.mainDom.find('#mlvup_box0').addClass('tada');
                }
            }

            clearTimeout(this.timer);
            this.timer = null;
        },
        
        /*参数，以前的等级，升级后的等级，百分比,是否满级
            debug mountpop.changBar(7, 11, 0.8, false)
        */
        changBar: function(lastLevel, nowLevel, percent, isFull) {
            var that = this;
            /*用锁防止它没看完就点*/
            xGame.islock = true;

            var times = nowLevel - lastLevel;
            var obj = that.mainDom.find('#mlvup_bar');
            var obj2 = that.mainDom.find('#mhouse_lv').find('b');
            var obj3 = that.mainDom.find('#lvup_horse_lv');
            var obj4 = that.mainDom.find('#mlvup_lv');

            /*没升级*/
            if (times == 0) {
                helper.animate(obj, {
                    'width': (percent * that.maxWidth + that.minWidth) + 'px'
                }, 500, 'Cubic.easeInOut', function() {
                    xGame.unlock();
                });
                
            } else {
                /*升级了的时候，未满级和满级时*/
                /*防止bug*/
                if (obj.width() == (that.maxWidth + that.minWidth)) {
                    obj.width(0);
                }

                obj.css({'width': (that.maxWidth + that.minWidth) + 'px', '-webkit-transition': 'width 0.5s ease'});

                that.mainDom.undelegate('#mlvup_bar', 'webkitTransitionEnd').delegate('#mlvup_bar', 'webkitTransitionEnd', function (e) {

                    if (e.target != obj[0]) {
                        return false;
                    }
                    times--;
                    lastLevel += 1;

                    if (times < 0) {
                        return;
                    } else {
                        obj2.html('Lv.' + lastLevel);
                        obj3.html('Lv.' + lastLevel);
                        obj4.html(lastLevel);
                        
                        if (times == 0) { //最后一下
                            /*判读如果满级的话就不现在最后的一点了*/
                            obj3.css('display', 'none').addClass('scale').css('display', 'block');
                            //最后一下抖动
                            setTimeout(function() {
                                that.mainDom.find('#mlvup_box0').addClass('tada');
                            },200);
                            if (isFull == 1) {
                                that.showLvupBtn();
                                xGame.unlock();
                                return;
                            }

                            that.mainDom.undelegate('#mlvup_bar', 'webkitTransitionEnd');
                            obj.css({'width': '0', '-webkit-transition': ''});

                            helper.animate(obj, {
                                'width': (percent * that.maxWidth + that.minWidth) + 'px'
                            }, 500, 'Cubic.easeInOut', function () {
                                xGame.unlock();
                            });
                        } else { //中间补全
                            that.createMc();
                            obj.css({'width': '0', '-webkit-transition': ''});
                            console.log(obj.width());   // 这一行代码必不可少，防止上一行代码不起作用
                            obj.css({'width': (that.maxWidth + that.minWidth) + 'px', '-webkit-transition': 'width 0.5s ease'});
                        }
                    }
                });
            }
        },
        
        createMc: function() {
            if (this.mc == null) {
            this.mc = new movieClipClass({
                "img": ximg(staticUrl + 'img/pop/mountpop_lvup.png'),
                "width": 200,
                "height": 306,
                "x": 107,
                "y": -30,
                "loop": false,
                "endDestory": false,
                'callback': function() {
                    mountpop.mc.obj.css('display', 'none');
                },
                "zindex": 100,
                "frames": [{
                    rect: [0, 0],
                    interval: 30
                },
                {
                    rect: [200, 0],
                    interval: 30
                },
                {
                    rect: [400, 0],
                    interval: 30
                },
                {
                    rect: [600, 0],
                    interval: 30
                },
                {
                    rect: [800, 0],
                    interval: 30
                },
                {
                    rect: [0, 306],
                    interval: 30
                },
                {
                    rect: [200, 306],
                    interval: 30
                },
                {
                    rect: [400, 306],
                    interval: 30
                },
                {
                    rect: [800, 306],
                    interval: 30
                }]
            });
                //开始播放
                this.mc.appendTo(this.mainDom.find('#mlvup_left'));
                this.mc.play();
            } else {
               this.mc.obj.css('display', 'block');
                this.mc.callback = function() {
                    mountpop.mc.obj.css('display', 'none');
                };
                this.mc.replay();
            }
        },
        
        //显示升级界面接口
        showLvupFunc: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) { 
                    case - 1 : 
                        xGame.toast.show(lang('studfarmpop_get_horse_fail'));
                        break;
                    case 2:
                        xGame.toast.show(lang('studfarmpop_lv_too_low'));
                        break;
                    case 1:
                        that.type = 2;
                        that.mainDom.attr('class', 'content show' + that.type);
                        that.lvupHorseData = res.info;
                        that.initLvupData();
                        
                        that.page = res.page;
                        that.pages = res.allpage;
                        that.horsesData = res.list;
                       
                        that.fillHorseInfo(res.info);
                        that.fillPackage(res.list);
                        
                        that.buildSeven(res.info);
                        that.fillBar(res.info, true);
                        
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'mount',
                'm': 'get_level_info',
                'id': that.currentHorseData.id
            },
            Func);
            Func = null;
        },
        
        //初始化升级相关的数据
        initLvupData: function() {
            this.tempData = {};
            this.totalExp = 0;
            this.eatenData = {
                1: 0,
                2: 0,
                3: 0,
                4: 0,
                5: 0,
                6: 0
            };
            this.showLvupMask();
        },
        
        showLvupBtn: function() {
            if (this.totalExp == 0 || this.lvupHorseData.is_full == 1) {
                this.mainDom.find('#mlvup_action').attr('class', 'pop_btn_big_disable btn');
                if (this.lvupHorseData.is_full == 1) {
                    this.mainDom.find('#mlvup_action').html(lang('is_full_s'));
                } else {
                    this.mainDom.find('#mlvup_action').html(lang('level_up'));
                }
            } else {
                this.mainDom.find('#mlvup_action').attr('class', 'pop_btn_big btn').html(lang('level_up'));
            }
        },
        
        fillBar: function(data, needSet) {
            var dom = this.mainDom.find('#mlvup_exp_dom');
            if (data.is_full == 1) {
                dom.find('#mlvup_exp_num').html(data.exp + '/' + data.exp);
            } else {
                dom.find('#mlvup_exp_num').html(data.exp + '/' + data.levelup_exp);
            }
            if (needSet) {
                dom.find('#mlvup_lv').html(data.level);
                if (data.is_full == 1) {
                    dom.find('#mlvup_bar').width(this.minWidth + this.maxWidth);
                } else {
                    dom.find('#mlvup_bar').width(this.minWidth + this.maxWidth * parseInt(data.exp) / parseInt(data.levelup_exp));
                }
            }
            dom = null;
        },
        
        buildSeven: function(data, lv) {
            var html = '';
            html += '<div id="mlvup_box0" class="first selected">';
            html +='<div class="pic" style="background-position:' + helper.getHorsePostion('big', data.mid) + ';"></div>';
            html += '<p class="name" style="color:' + helper.getItemColor(data.quality) + '">' + data.name + '</p>';
            html += '<p class="lv" id="lvup_horse_lv">Lv.' + (lv != undefined ? lv : data.level) + '</p>';
            html += '</div>';
            
            for (var i = 1;i < 7;i ++) {
                html += '<div id="mlvup_box' + i + '" class="second c_s_i_bj1"></div>';
            }
            this.mainDom.find('#mhorse_seven').html(html);
            html = null;
        },
        
        showIndexFunc: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) { 
                    case - 1 : 
                        xGame.toast.show(lang('mountpop_get_hero_fail'));
                        break;
                    case 2:
                        xGame.toast.show(lang('studfarmpop_lv_too_low'));
                        break;
                    case 1:
                        that.type = 1;//默认显示坐骑界面
                        that.mainDom.attr('class', 'content show' + that.type);
                    
                        that.horsesData = res.mlist;
                        
                        that.page = res.page;
                        that.pages = res.allpage;
                        
                        //先更新英雄数据
                        //that.fillHeroList(res.general);
                        that.fillGInfo(res.info);
                        //再更新马匹数据
                        that.fillHorseInfo(res.minfo);
                        that.fillPackage(res.mlist);
                        
                        
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            
            loadContents({
                'c': 'mount',
                'm': 'get_info_by_id',
                'gid': that.heroData.id,
                'page': that.page,
                'mid': that.lvupHorseData.id
            }, Func);
            Func = null;
        },
        
        bindAll: function() {
            var that = this;
            
            that.mainDom.find('#mountpop_back').unbind('touchend').bind('touchend',
            function() {
                that.showIndexFunc();
            });
            
            that.mainDom.find('#mlvup_btn').unbind('touchend').bind('touchend',
            function() {
                that.showLvupFunc();
            });
            
            that.mainDom.find('#mhorse_btn_left').unbind('touchend').bind('touchend',
            function() {
                if (that.myScroll != null) {
                    that.myScroll.scrollToPage('prev', 0);
                }
                return false;
            });
            
            that.mainDom.find('#mhorse_btn_right').unbind('touchend').bind('touchend',
            function() {
                if (that.myScroll != null) {
                    that.myScroll.scrollToPage('next', 0);
                }
                return false;
            });
            
            that.mainDom.undelegate('.heroitem', 'click').delegate('.heroitem', 'click',
            function() {
                if ($(this).hasClass('selected')) {
                    return false;
                }
                that.getHeroInfo($(this));
            });
            
            that.mainDom.undelegate('#go_studfram', 'touchend').delegate('#go_studfram', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                mountpop.hide();
                xGame.showpop('studfarm');
            });
            
            that.mainDom.undelegate('#mhorse_info', 'touchend').delegate('#mhorse_info', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.mainDom.find('#mhorse_info_dom').css('display', 'block');
            });
            
            that.mainDom.undelegate('#mhorse_info_hide', 'touchend').delegate('#mhorse_info_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.mainDom.find('#mhorse_info_dom').css('display', 'none');
            });
            
            that.mainDom.undelegate('#mlvup_info', 'touchend').delegate('#mlvup_info', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.mainDom.find('#mlvup_info_dom').css('display', 'block');
            });
            
            that.mainDom.undelegate('#mlvup_info_hide', 'touchend').delegate('#mlvup_info_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.mainDom.find('#mlvup_info_dom').css('display', 'none');
            });
            
            that.mainDom.undelegate('#mhorse_prev', 'touchend').delegate('#mhorse_prev', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page == 1) { //不设置属性
                    return false;
                }
                if (that.type == 1) {
                    that.flipPageFunc(that.page -1);
                } else {
                    that.flipPageFunc(that.page - 1, mountpop.lvupHorseData.id);
                }
            });
            
            that.mainDom.undelegate('#mhorse_next', 'touchend').delegate('#mhorse_next', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page == that.pages) { //不设置属性
                    return false;
                }
                if (that.type == 1) {
                    that.flipPageFunc(that.page + 1);
                } else {
                    that.flipPageFunc(that.page + 1, mountpop.lvupHorseData.id);
                }
            });
            
            that.mainDom.undelegate('#mlvup_box0', 'touchend').delegate('#mlvup_box0', 'touchend',
            function() {
                if (!$(this).hasClass('selected')) {
                    that.mainDom.find('#mhorse_seven').find('.selected').removeClass('selected');
                    $(this).addClass('selected');
                    that.fillHorseInfo(that.lvupHorseData);
                }
            });
            
            that.mainDom.undelegate('#mlvup_action.pop_btn_big', 'touchend').delegate('#mlvup_action.pop_btn_big', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var str = that.getStr();
                if (str != '') {
                    xGame.confirm.show(lang('level_up'), lang('mountpop_lvup_ask', [that.lvupHorseData.name]), 2,
                    function() {
                        xGame.confirm.hide();
                        that.lvupFunc(str);
                    },
                    function() {
                        xGame.confirm.hide();
                    }, lang('level_up'), lang('cancle'));
                }
            });
            
            that.bindHorse();
        },
        
        getStr: function() {
            var str = '';
            for (var i = 1;i < 7;i ++) {
                if (this.eatenData[i] != 0) {
                    str += (this.eatenData[i] + ',');
                }
            }
            if (str != '') {
                str += ']';
                str = str.replace(',]', '');
            }
            return str;
        },

        //绑定马匹移动的
        bindHorse: function() {
            var that = this;

            var ex = 0;
            var ey = 0;
            var ox = 0;
            var oy = 0;
            var target = null;
            var pos;
            var site;
        
            that.moveDom.undelegate('.moveitem', 'touchstart').delegate('.moveitem', 'touchstart',
            function(event) {

                if (event.target.className == 'name') {
                    return false;
                }
                
                pos = $(this).attr('pos');
            
                //如果不是当前选中的，修改显示数据
                if (that.currentHorseData.id != $(this).attr('hid')) { 
                    that.mainDom.find('#horse_box_dom').find('.selected').removeClass('selected');
                    that.mainDom.find('#mhorse_five').find('.selected').removeClass('selected');
                    that.mainDom.find('#mhorse_seven').find('.selected').removeClass('selected');
                    if ($(this).hasClass('unequip')) {
                        var data = that.horsesData[$(this).attr('index')];
                        that.fillHorseInfo(data);
                        that.mainDom.find('#horse_box_dom').find('#hbox' + data.id).addClass('selected');
                    } else {
                        //坐骑界面需要这些数据
                        if (that.type == 1) {
                            var data = that.heroData['mid' + pos];
                            that.fillHorseInfo(data);
                            that.mainDom.find('#mhorse_five').find('#mhorse_box' + pos).addClass('selected');
                        } else {//如果点击的是升级位上的马匹（要升级的马匹已经单独处理了，这里只用考虑升级位置）
                            var id = $(this).attr('hid');
                            //点击的本页的马匹
                            if (that.tempData[id] == undefined) {
                                var data = that.horsesData[$(this).attr('index')];
                                that.fillHorseInfo(data);
                            } else {//点击的不是本页的马匹
                                var data = that.tempData[id];
                                that.fillHorseInfo(data);
                            }
                            site = $(this).attr('site');
                            that.mainDom.find('#mhorse_seven').find('#mlvup_box' + site).addClass('selected');
                        }
                    }
                }
                //不可装备的道具禁止拖拽
                if ($(this).hasClass('islock')) { 
                    return false;
                }
                
                target = $(this);
                
                ox = parseInt(event.touches[0].pageX);
                oy = parseInt(event.touches[0].pageY);
                
                
                //当前的位置，按照已经装备的和未装备的区分
                if ($(this).hasClass('unequip')) {
                    that.touchPosData = that.boxPos[pos];
                } else {
                    if (that.type == 1) {
                        that.touchPosData = that.equipPos[pos];
                    } else {
                        site = $(this).attr('site');
                        that.touchPosData = that.lvupPos[site];
                    }
                }
                
                ex = parseInt(that.touchPosData.left);
                ey = parseInt(that.touchPosData.top);
                
                ox = parseInt(event.touches[0].pageX);
                oy = parseInt(event.touches[0].pageY);
                
                //大图片特殊处理
                if ($(this).hasClass('big')) {
                    ex = (ox - that.moveDom.offset().left - 50);
                    ey = (oy - that.moveDom.offset().top - 50);
                    //console.log(ex);
                    //console.log(ey);
                    $(this).css({
                        'background-position': helper.getHorsePostion('small', $(this).attr('mid')),
                        'left': ex + 'px',
                        'top': ey + 'px'
                    }).addClass('clone');
                }
                
                $(this).css('z-index', that.zIndex++).addClass('ismoving');
                target = $(this);
            });
        
            this.e.unbind('touchmove').bind('touchmove',function(event) {
                if (target) {
                    var nX = parseInt(event.touches[0].pageX);
                    var nY = parseInt(event.touches[0].pageY);

                    var newX = nX - ox;
                    var newY = nY - oy;
                    var realX = ex + newX;
                    var realY = ey + newY;
                    target.css({
                        left: realX + 'px',
                        top: realY + 'px'
                    });
                    
                }
            });
            
            this.moveDom.undelegate('.moveitem.ismoving', 'touchend').delegate('.moveitem.ismoving', 'touchend',
            function(event) {
                var myx = parseInt(target.css('left').replace('px', ''));
                var myy = parseInt(target.css('top').replace('px', ''));
                
                target = null;
                
                /*如果坐标最后都没变化，大图片特殊处理*/
                if (myx == ex && myy == ey) {
                    that.removeSign($(this));
                    return false;
                }
            
                that.checkMain($(this), myx, myy);
            });
        },
        
        //清除标记
        removeSign: function(obj) {
            obj.removeClass('ismoving');
            if (obj.hasClass('big')) {
                obj.removeClass('clone').css({
                    'left': this.touchPosData.left + 'px',
                    'top': this.touchPosData.top + 'px'
                });
            }
        },
        
        //检测的主方法
        checkMain: function(obj, x, y) {
            var that = this;
            var left;
            var top;
            if (that.checkPos(obj, x, y)) {
                //中间的特殊处理，写死
                if (that.nearlyPosData.pos == 0) {
                    left = '-275';
                    top = '35';
                } else {
                    left = that.nearlyPosData.left;
                    top = that.nearlyPosData.top;
                }
                
                obj.animate({
                    'left': left + 'px',
                    'top': top + 'px'
                }, 100, 'linear', function() {
                    //到位置上后再显示该显示的武将信息
                    if (that.type == 1) {
                        that.equipFunc(that.nearlyPosData.pos, $(this));
                    } else {
                        that.lvupEquip(obj);
                    }
                });
            } else {
                //检测是否是卸下操作
                if (that.checkUnequip(obj, x, y)) {
                    if (that.type == 1) {
                        that.unequipFunc(obj);
                    } else {
                        that.lvupUnequip(obj);
                    }
                } else {
                    that.errorFunc(obj);
                }
            }
        },
        
        //升级时的装备方法
        lvupEquip: function(obj) {
            var that = this;
            this.removeSign(obj);
            
            var site = this.nearlyPosData.pos;//想移动到的site
            var osite = obj.attr('site');//当前的site
            //清理所有选择状态
            this.mainDom.find('#mhorse_seven').find('.selected').removeClass('selected');
            this.mainDom.find('#horse_box_dom').find('.selected').removeClass('selected');
            
            //如果想移动的位置没有马直接设置
            if (this.eatenData[site] == 0) {
                var id = this.currentHorseData.id;
                this.eatenData[site] = id;
                if (osite != undefined) {
                    this.eatenData[osite] = 0;
                    this.totalExp -= parseInt(this.currentHorseData.toexp);
                    this.mainDom.find('#mhorse_seven').find('#mlvup_box' + osite).attr('class', 'second c_s_i_bj1');
                }
                //设置新site
                obj.attr('site', site).removeClass('unequip').addClass('eaten');
                this.tempData[id] = this.currentHorseData;
                this.totalExp += parseInt(this.currentHorseData.toexp);
                
                //设置颜色
                var quality = this.currentHorseData.quality;
                this.mainDom.find('#mhorse_seven').find('#mlvup_box' + site).attr('class', 'second c_s_i_bj' + quality);
            } else {//如果有，交换
                //两种情况，一种是已经在升级位置上的，一种是背包里的马覆盖
                if (obj.hasClass('eaten')) {//交换的那种情况
                    //交换数据
                    var id = this.eatenData[site];
                    var oid = this.eatenData[osite]
                    this.eatenData[site] = oid;
                    this.eatenData[osite] = id;
                    
                    //交换位置并且设置新site
                    obj.attr('site', site);
                    var nearObj = this.moveDom.find('#horse' + id);
                    nearObj.animate({
                        'left': that.lvupPos[osite].left + 'px',
                        'top': that.lvupPos[osite].top + 'px'
                    }, 100, 'linear', null).attr('site', osite);
                    
                    //设置颜色
                    var quality = this.currentHorseData.quality;
                    this.mainDom.find('#mhorse_seven').find('#mlvup_box' + site).attr('class', 'second c_s_i_bj' + quality);
                    var oquality = this.tempData[oid].quality;
                    this.mainDom.find('#mhorse_seven').find('#mlvup_box' + osite).attr('class', 'second c_s_i_bj' + oquality);
                    
                } else {//背包里拖上去的，覆盖数据，马如果是当前页飞过去，不是就消失
                    var id = this.currentHorseData.id;
                    var oid = this.eatenData[site];
                    this.eatenData[site] = id;
                    obj.attr('site', site).removeClass('unequip').addClass('eaten');
                    this.tempData[id] = this.currentHorseData;
                    this.totalExp += parseInt(this.currentHorseData.toexp);
                    this.totalExp -= parseInt(this.tempData[oid].toexp);
                    delete this.tempData[oid];
                    
                    var oldObj = this.moveDom.find('#horse' + oid);
                    var pos = oldObj.attr('pos');
                    if (this.isCurrentPage(oid)) {//马如果是当前页飞过去
                        oldObj.removeClass('eaten').addClass('unequip').removeAttr('site')
                        oldObj.animate({
                            'left': that.boxPos[pos].left + 'px',
                            'top': that.boxPos[pos].top + 'px'
                        }, 100, 'linear', null);
                    } else {//不是就消失
                        oldObj.remove();
                    }
                    //设置颜色
                    var quality = this.currentHorseData.quality;
                    this.mainDom.find('#mhorse_seven').find('#mlvup_box' + site).attr('class', 'second c_s_i_bj' + quality);
                }
            }
            //console.log(this.eatenData);
            //给想要移动的位置选中状态，必须在设置完颜色之后
            this.mainDom.find('#mhorse_seven').find('#mlvup_box' + site).addClass('selected');
            that.showLvupMask();
        },
        
         //升级时的卸下方法
        lvupUnequip: function(obj) {
            var that = this;
            this.removeSign(obj);
            var site = obj.attr('site');//当前的site
            var  id = this.eatenData[site];
            this.eatenData[site] = 0;
            this.totalExp -= parseInt(this.tempData[id].toexp);
            delete this.tempData[id];
            //当前页面的回到当前位置，不是当前页面的直接消失，数据都得清空this.eatenData[site]
            if (this.isCurrentPage(obj.attr('hid'))) {
                var pos = obj.attr('pos');
                //设置选中状态
                this.mainDom.find('#mhorse_seven').find('.selected').removeClass('selected');
                this.mainDom.find('#horse_box_dom').find('#hbox' + id).addClass('selected');
                
                obj.removeClass('eaten').addClass('unequip').removeAttr('site');
                obj.animate({
                    'left': that.boxPos[pos].left + 'px',
                    'top': that.boxPos[pos].top + 'px'
                }, 100, 'linear', null);
            } else {
                obj.remove();
                //设置选中状态
                this.mainDom.find('#mhorse_seven').find('.selected').removeClass('selected');
                var firstData = this.horsesData[0];
                this.fillHorseInfo(firstData);
                this.mainDom.find('#horse_box_dom').find('#hbox' + firstData.id).addClass('selected');
            }
            this.mainDom.find('#mhorse_seven').find('#mlvup_box' + site).attr('class', 'second c_s_i_bj1');
            that.showLvupMask();
        },
        
        //显示可以升多少级
        showLvupMask: function() {
            var lvupExp = parseInt(this.lvupHorseData.levelup_exp);//当前要升级的马升级需要的经验
            var nowExp = parseInt(this.lvupHorseData.exp);
            var dom = this.mainDom.find('#mlvup_exp_dom');
            if (this.lvupHorseData.is_full == 1) {
                this.totalExp = 0;
            }
            if (this.totalExp == 0) {
                dom.find('#mlvup_mask').css('display', 'none').width(0);
                dom.find('#mlvup_lizi').css('display', 'none').width(0);
                dom.find('#mlvup_exp_num').html(nowExp + '/' + lvupExp);
            } else {
                var percent = ((this.totalExp + nowExp) / lvupExp) > 1 ? 1 : ((this.totalExp + nowExp) / lvupExp);
                var width = 310 * percent;//310是通过计算得到的
                dom.find('#mlvup_mask').width(width).css('display', 'block');
                dom.find('#mlvup_lizi').width(width).css('display', 'block');
                dom.find('#mlvup_exp_num').html(nowExp + '<em>(+' + this.totalExp + ')</em>' + '/' + lvupExp);
            }
            //console.log(this.totalExp);
            this.showLvupBtn();
            dom = null;
            lvupExp = null;
            nowExp = null;
        },
        
        isCurrentPage: function(id) {
            var status = 0;
            for (var i = 0,len = this.horsesData.length;i < len;i ++) {
                if (id == this.horsesData[i].id) {
                    status = 1;
                }
            }
            if (status == 1) {
                return true;
            } else {
                return false;
            }
        },
        
        //装备和卸载发生错误时的统一方法，回到原位置
        errorFunc: function(obj) {
            var that = this;
            var left;
            var top;
            //中间的特殊处理，写死
            if (that.touchPosData.pos == 0) {
                left = '-275';
                top = '35';
            } else {
                left = that.touchPosData.left;
                top = that.touchPosData.top;
            }
            obj.animate({
                'left': left + 'px',
                'top': top + 'px'
            }, 100, 'linear', function() {
                that.removeSign(obj);
            });
        },
        
        /*这个功能的作用是传进参数，检测边界，
         * 返回true时代表有位置可以到达，再设置that.nearlyX和that.nearlyY完成操作；
         * 返回false时就代表直接返回原来的位置
         */
        checkPos: function(obj, x, y) {
            var that = this;

            /*结果集*/
            var result = [];
            
            /*第一步，告诉程序有几个点可以移动，加入自己和根据当前type看是用equipPos还是lvupPos*/
            //强制加入自己,自己的id是100
            result.push({
                'id': '100',
                'distance': that.getDistance((that.touchPosData.left - x), (that.touchPosData.top - y))
            });
            
            //区分当前是那个界面
            if (that.type == 1) {
                //装备界面只有5个点可以加载，其中大的比较特殊，大的单独处理
                //185/2 - 100/2， 228 /2 - 100/2
                if (x < - 180 && x > -355 && y > -60 && y < 150) {
                    that.nearlyPosData = that.equipPos[0];
                    //如果一定的就是大图片
                    if (obj.hasClass('big')) {
                        return false;
                    } else {
                        return true;
                    }
                }
                
                for (var i = 1; i < 5; i++) {
                    result.push({
                        'id': i,
                        'distance': that.getDistance((that.equipPos[i].left - x), (that.equipPos[i].top - y))
                    });
                }
            } else {
                for (var i = 1; i < 7; i++) {
                    result.push({
                        'id': i,
                        'distance': that.getDistance((that.lvupPos[i].left - x), (that.lvupPos[i].top - y))
                    });
                }
            }
            
            /*按属性排大小，方法写在base.js里面*/
            result.sort(helper.compare("distance"));
            //console.log(result);
            if (result.length != 0) {
                //如果最小的是自己，直接返回false
                if (result[0]['id'] == 100) {
                    return false;
                } else {
                    if (that.type == 1) {
                        that.nearlyPosData = that.equipPos[result[0]['id']];
                    } else {
                        that.nearlyPosData = that.lvupPos[result[0]['id']];
                    }
                }
                
                var myDistance = that.getDistance((that.nearlyPosData.left - x), (that.nearlyPosData.top - y));

                //console.log('距离 = ' + myDistance);
                //console.log('最近的posid = ' + that.nearlyPosId);
                if (myDistance < that.distance * 3 / 4) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        },
        
        //这个方法用来检测没有最近的位置移动后看是不是卸载，返回true就调用卸载功能，false反之
        checkUnequip: function(obj, x, y) {
            //如果已经是未装备的，就返回false
            if (obj.hasClass('unequip')) {
                return false;
            } else {
                if (x > -60) {
                    return true;
                } else {
                    return false;
                }
            }
        },
        
        getDistance: function(x, y) {
            return Math.sqrt(x * x + y * y);
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#mountpop_hide').unbind('touchend');
                this.e.unbind('touchmove');
                this.e.remove();
                this.e = null;
            }
            if (this.mainDom) {
                this.mainDom.find('#mountpop_back').unbind('touchend');
                this.mainDom.find('#mlvup_btn').unbind('touchend');
                this.mainDom.find('#mhorse_btn_left').unbind('touchend');
                this.mainDom.find('#mhorse_btn_right').unbind('touchend');
                this.mainDom.undelegate('.heroitem', 'click');
                this.mainDom.undelegate('#go_studfram', 'touchend');
                this.mainDom.undelegate('#mhorse_info', 'touchend');
                this.mainDom.undelegate('#mhorse_info_hide', 'touchend');
                this.mainDom.undelegate('#mlvup_info', 'touchend');
                this.mainDom.undelegate('#mlvup_info_hide', 'touchend');
                this.mainDom.undelegate('#mhorse_prev', 'touchend');
                this.mainDom.undelegate('#mhorse_next', 'touchend');
                this.mainDom.undelegate('#mlvup_box0', 'touchend');
                this.mainDom.undelegate('#mlvup_action.pop_btn_big', 'touchend');
                this.mainDom.remove();
                this.mainDom = null;
            }
            if (this.moveDom) {
                this.moveDom.undelegate('.moveitem', 'touchstart');
                this.moveDom.undelegate('.moveitem.ismoving', 'touchend');
                this.moveDom.remove();
                this.moveDom = null;
            }
        },
        
        hide: function() {
        
            xGame.unlock();
            this.isshow = false;
            this.type = null;
            
            if (this.myScroll != null) {
                this.myScroll.destroy();
                this.myScroll = null;
            }
            
            this.boxPos = null;
            this.equipPos = null;
            this.lvupPos = null;
            this.zIndex = null;
            this.touchPosData = null;
            this.nearlyPosData = null;
            this.distance = null;
            
            this.currentHorseData = null;
            this.horsesData = null;
            this.heroData = null;
            this.page = null;
            this.pages = null;
            this.maxWidth = null;
            this.minWidth = null;
            this.eatenData = null;
            this.lvupHorseData = null;
            this.tempData = null;
            this.totalExp = null;
            this.mc = null;
            
            this.unbindAll();
            if (this.isTimeout) {
                clearTimeout(this.isTimeout);
                this.isTimeout = null;
            }
            
        },
    };
})();