/* 武将进阶 */
(function() {
    advancedPopClass = function() {
        this.e;
        this.isshow = false;
        
        this.topDom;
        this.moveDom;
        this.lbDom;//左下英雄dom
        this.heroScroller;//英雄scroller
        this.heroSkillDom;//技能信息dom
        this.heroInheritDom;//传承dom
        this.heroSkillLevelData;//本地保存的技能达成条件，用来判断勾
        this.viewGid;//当前查看的武将id
        this.viewSkillId;//当前技能id
        this.need_level;//进阶需要四个属性的等级
        
        this.gid1; //9号位置武将id
        this.gid2; //10号位置
        this.heroData; //将领的基本信息
        this.heroDataByGid; //将领的基本信息,以武将id为键
        this.touchHeroData; //当前点击的英雄信息
        this.ruleData;

        this.touchGid; //当前点击的英雄id
        this.touchPos;
        this.canMove; //可以移动的集合
        this.heroPosList; //8个武将坐标集合
        this.boxPosList; //2个容器坐标集合
        this.nearlyX; //最近元素的x坐标
        this.nearlyY; //最近元素的y坐标
        this.nearlyPosId; //最近的元素的posid
        this.zIndex; //层级
        this.distance; //检测范围
    };
    
    advancedPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {
                if (res.status == 1) {
                    that.initPage(res);
                    xGame.showpop('system', {'pop': 'advanced'});
                } else {
                    var msg = '';
                    switch(parseInt(res.status)) {
                        case 2:
                             msg = lang('lv_can_not_open_func');
                            break;
                        case -1:
                             msg = lang('get_had_hero_list_fail');
                            break;
                        case -2:
                             msg = lang('advancedpop_get_bd_skill_fail');
                            break;
                        case -3:
                             msg = lang('advancedpop_get_bd_skill_lvup_fail');
                            break;
                        case -4:
                             msg = lang('advancedpop_get_advanced_info_fail');
                            break;
                        case -5:
                             msg = lang('advancedpop_get_hero_equip_fail');
                            break;
                        case -6:
                             msg = lang('advancedpop_get_left_item_fail');
                            break;
                        default:
                            msg = lang('undefined_error');
                            break;
                    }
                    xGame.toast.show(msg);
                    $('#worldloading').hide();
                }
            };
            loadContents({
                'c': 'advanced',
                'm': 'index'
            }, initRender, true);
            
            initRender = null;
        },
        
        //设置页面
        initPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('advanced');
            that.e.find('.content').find('#advancedpop_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                advancedpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });
            
            that.topDom = that.e.find('#advancedpop_top');
            that.moveDom = that.e.find('#move_dom');
            that.lbDom = that.e.find('#advancedpop_lbottom');
            that.heroSkillDom = that.e.find('#advancedpop_hero_dom');
            that.heroInheritDom = that.e.find('#advancedpop_inherit_dom');
            
            //进阶传承相关，必须放在前面
            that.zIndex = 10;
            that.distance = that.getDistance(100, 100);
            
           that.heroData = res.list;
            
            //初始化位置参数
            that.heroPosList = {};
            that.heroDataByGid = {};
            var x;
            for (var i = 0; i < 8; i++) {
                x = 112 * i + 7;
                that.heroPosList['pos' + (i + 1)] = {
                    'left': x,
                    'top': '0',
                    'style': 'left:' + x + 'px;top:0px;',
                };
                
                if (res.list[i] != undefined) {
                    that.heroDataByGid[res.list[i].id] = res.list[i];
                }
                
            }
            that.heroPosList['pos9'] = {
                'left': '495',
                'top': '220',
                'style': 'left:495px;top:220px;',
                'id': '9'
            };
            that.heroPosList['pos10'] = {
                'left': '732',
                'top': '220',
                'style': 'left:732px;top:220px;',
                'id': '10'
            };
            x = null;
            that.boxPosList = {
                'pos9': {
                    'left': '495',
                    'top': '220',
                    'style': 'left:495px;top:220px;',
                    'id': '9'
                },
                'pos10': {
                    'left': '732',
                    'top': '220',
                    'style': 'left:732px;top:220px;',
                    'id': '10'
                }
            }

            that.canMove = {
                'pos9': 'block',
                'pos10': 'block'
            }
            
            that.gid1 = 0;
            that.gid2 = 0;
            
            
            //武将进阶相关
            that.e.find('#advancedpop_wrap').attr('class', 'content hero');
            
            that.buildTop(res.list);
            that.buildHero(res.general);
            that.filleSkillInfo(res.skill_info);
            that.filleSkillLevelUp(res.quality_info);
            that.showResource(res.resource);
            that.showBtn(res.level_up);
            xGame.updateCd('advanced', res.cd);
            
            that.bindAll();
            
            that.e.show();
            $('#worldloading').hide();
            
            that.scrollerRefresh();
            
            
        },
        
        //当前技能的升级要求
        filleSkillLevelUp: function(data) {
            var sData = this.heroSkillLevelData;
            var html1 = '';
            var span = '';
            data.skill_level = parseInt(data.skill_level);
            this.need_level = data.skill_level;
            for (var i = 1;i < 5;i ++) {
                if (data.skill_level  <= sData[i].level) {
                    span = '<span class="reach"></span>';
                } else {
                    span = '<span class="unreach">(' + lang('not_reached') + ')</span>';
                }
                html1 += '<li id="skill_need' + i + '">' + sData[i].name + ' ' + lang('advancedpop_reach_lv', [data.skill_level]) + span + '</li>';
            }
            
            this.heroSkillDom.find('#lvup_rule').html(html1);
            span = null;
            html1 = null;
            sData = null;
            
            var attr = [lang('advancedpop_o_wuli'), lang('advancedpop_o_zhili'), lang('advancedpop_o_tili'), lang('advancedpop_o_hp')];
            var back_attr = [data.wuli, data.tili, data.tili, data.hp];
            var html2 = '';
            for (var j = 0;j < 4;j ++) {
                html2 += '<li>' + attr[j] + '<em>+' + back_attr[j] + '</em></li>';
            }
            
            this.heroSkillDom.find('#lvup_plus').html(html2);
            attr = null;
            html2 = null;
            back_attr = null;
            
            var classes = ['', 'gem2', 'gem3', 'osoul', 'reputation'];
            var html3 = '';
            for (var k = 1;k < 5;k ++) {
                if (data['use_value' + k] != 0) {
                    html3 += '<li><span class="' + classes[data['use_type' + k]] + '">' + this.getWan(data['use_value' + k]) + '</span></li>';
                }
            }
            this.heroSkillDom.find('#lvup_use').html(html3);
            
            html3 = null;
            classes = null;
            
            this.heroSkillDom.find('#tips').html(lang('advancedpop_advanced_need_lv', [data.general_level]));
            if (parseInt(data.general_level) > parseInt(this.heroDataByGid[this.viewGid].level)) {
                this.heroSkillDom.find('#tips').attr('class', 'tips buzu');
            } else {
                this.heroSkillDom.find('#tips').attr('class', 'tips');
            }
        },
        
        //当前被动技能的信息
        filleSkillInfo: function(data) {
            //填充详细信息
            this.heroSkillDom.find('#skill_box').attr('class', 'q' + data.quality);
            this.heroSkillDom.find('#skill_img').attr({'class': 'skill' + data.type, 'stype' : data.type});
            this.heroSkillDom.find('#skill_name').html(data.name + '<small>(' + lang('advancedpop_bd_skill') + ')</small>').css('color', helper.getItemColor(data.quality));
            this.heroSkillDom.find('#skill_lv').html('Lv.' + data.level);
            if (data.is_full == 0) {
                this.heroSkillDom.find('#skill_attr').html(data.add_name + '+' + data.value + '(' + lang('next_lv') + data.next_value + ')');
                this.heroSkillDom.find('#skill_cost').html('x' + data.next_use);
                this.heroSkillDom.find('.skill_cost').css('display', 'block');
                this.heroSkillDom.find('#skill_lvup').css('display', 'block');
            } else {
                this.heroSkillDom.find('#skill_attr').html(data.add_name + '+' + data.value + lang('full_lv'));
                this.heroSkillDom.find('.skill_cost').css('display', 'none');
                this.heroSkillDom.find('#skill_lvup').css('display', 'none');
            }
        },
        
        //显示资源
        showResource: function(data) {
            var dom = this.heroSkillDom.find('#resource_dom');
            if (data.freeleap != undefined) {
                this.numShine(dom.find('.tfcard'), data.freeleap);
            }
            if (data.jade2 != undefined) {
                this.numShine(dom.find('.gem2'), data.jade2);
            }
            if (data.jade3 != undefined) {
                this.numShine(dom.find('.gem3'), data.jade3);
            }
            if (data.reputation != undefined) {
                this.numShine(dom.find('.reputation'), data.reputation);
            }
            if (data.soul != undefined) {
                this.numShine(dom.find('.osoul'), data.soul);
            }
            dom = null;
        },
        
        //这里判断数值是不是有变化了
        numShine: function(dom, num) {
            var that = this;
            if (dom.html() != that.getWan(num) && dom.html() != '') {
                xGame.doAnimate2(dom, 'changcolor', 100, 7,
                function() {
                    dom.html(that.getWan(num));
                });
            } else {
                dom.html(that.getWan(num)).css('display', 'block');
            }
        },
        
        getWan: function(num) {
            if (parseInt(num) > 1000000) {//超过100W就显示单位
                return Math.round(parseInt(num) / 10000) + lang('10k');
            } else {
                return num;
            }
        },
        
        //构建两个武将列表
        buildTop: function(data) {
            /*8个位置*/
            var html1 = '';
            var html2 = '';
            var pos = 0;
            for (var i = 0;i < 8;i ++) {//固定8个的
                if (data[i] != undefined) {
                    pos += 1;
                    html1 += '<li class="hero' + data[i].quality + '" id="hero' + data[i].id + '"><span>' + data[i].name + '</span></li>';
                    html2 += '<span posid="' + pos + '" gid="' + data[i].id + '" id="move_pos' + pos + '" style="background-image:url(' + xGame.avatar(data[i].image) + ');' + this.heroPosList['pos' + pos].style + '"></span>';
                } else {
                    html1 += '<li class="none"></li>';
                }
            }
            this.topDom.find('#advancedpop_eight_hero').html(html1);
            this.moveDom.html(html2);
            html1 = null;
            html2 = null;
            pos = null;
            
            /*滑动*/
            var html3 = '';
            var heroNum = 0;
            for (var j = 0;j < data.length;j ++ ) { 
                html3 += '<li id="hero' + data[j].id + '" class="hero' + data[j].quality + ' heroitem" heroid="' + data[j].id + '" >';
                html3 += '<span class="avatar" style="background:url(' + xGame.avatar(data[j].image) + ') center center no-repeat;' + '"></span>';
                html3 += '<p class="name">' + data[j].name + '</p>';
                html3 += '</li>';
                heroNum += 1;
            }
            this.topDom.find('#advancedpop_list').html(html3);
            
            if (heroNum > 3) {
                this.heroScroller = xGame.scroller('advancedpop_scroller', heroNum * 120);
            }
            
            html3 = null;
            heroNum = null;
        },
        
        //iscroller刷新
        scrollerRefresh: function() {
            if (this.heroScroller != null) {
                //todo 判断回来的时候让当前武将滑到可以看见，可做可不做
                this.heroScroller.refresh();
            }
        },
        
        getDistance: function(x, y) {
            return Math.sqrt(x * x + y * y);
        },
        
        /*显示玩家信息，以及被动技能*/
        buildHero: function(data) {
            /*武将信息*/
            this.lbDom.find('#hero_type').html(helper.getItemVocation(data.type));
            this.lbDom.find('#hero_lv').html('Lv.' + data.level);
            this.lbDom.find('#hero_name').html(data.name);
            if (data.turn > 0) {
                this.lbDom.find('#hero_turn').html(data.turn).show();
            } else {
                this.lbDom.find('#hero_turn').hide();
            }
            this.lbDom.find('#hero_img').css('backgroundImage', 'url(' + xGame.avatar(data.image, true) + ')');

            this.lbDom.find('#star_num').css('width', parseInt(data.star) * 18 + 'px');

            this.lbDom.find('#hero_h').html(data.hp);
            this.lbDom.find('#hero_a').html(data.wuli + ' +' + data.wuliup);
            this.lbDom.find('#hero_t').html(data.talent == null ? lang('no') : data.talent);
            this.lbDom.find('#hero_k').html(data.zhili + ' +' + data.zhiliup);
            if (data.skill.length != 0) {
                this.lbDom.find('#hero_s').html(data.skill == null ? lang('no') : data.skill);
            }
            this.lbDom.find('#hero_p').html(data.tili + ' +' + data.tiliup);
            
            this.lbDom.find('ul').attr('class', 'hero_info_detail ' + (data.type == 1 ? 'wj': 'ms'));
            
            //武将品级
            this.lbDom.find('#hero_body').attr('class', 'hero_body hero_quality q' + data.quality);
            
            /*四个属性信息*/
            this.heroSkillLevelData = {};
            for (var i = 1; i <= 4; i++) {
                this.heroSkillLevelData[i] = {};
                this.heroSkillLevelData[i].level = parseInt(data['skill' + i + '_level']);
                this.heroSkillLevelData[i].name = data['skill' + i + '_name'];
                this.lbDom.find('#skill' + i).attr('class', 'eid' + i + ' skill_box' + ' q' + data['skill' + i + '_quality'] + (i == 1 ? ' selected' : ''));
            }
            
            this.viewGid = data.id;
            this.viewSkillId = 1;
            this.heroDataByGid[data.id].quality = data.quality;
            
            this.topDom.find('#advancedpop_list').find('.selected').removeClass('selected');
            this.topDom.find('#advancedpop_list').find('#hero' + data.id).addClass('selected');
            
            //组装武将信息的时候并且是将领页面的时候初始化数据
            //达到的效果是我不换将的时候不清理页面，我只要一换将就清理数据
            if (this.e.find('#advancedpop_wrap').hasClass('hero')) {
                this.gid1 = data.id;
                this.canMove['pos9'] = data.id;
                this.moveDom.find('#move_pos9').remove();
                this.moveDom.append('<span posid="9" gid="' + data.id + '" id="move_pos9" style="background-image:url(' + xGame.avatar(data.image) + ');' + this.heroPosList['pos9'].style + '"></span>');
                
                this.gid2 = 0;
                this.canMove['pos10'] = 0;
                this.moveDom.find('#move_pos10').remove();
                
                this.setInheritBoxBg();
            }
            
        },
        
        setInheritBoxBg: function() {
            if (this.gid1 != 0) {
                this.heroInheritDom.find('#pos9').attr('class', 'inherit_box q' + this.heroDataByGid[this.gid1].quality).html('');
            } else {
                this.heroInheritDom.find('#pos9').attr('class', 'inherit_box').html(lang('advancedpop_move_to'));
            }
            
            if (this.gid2 != 0) {
                this.heroInheritDom.find('#pos10').attr('class', 'inherit_box q' + this.heroDataByGid[this.gid2].quality).html('');
            } else {
                this.heroInheritDom.find('#pos10').attr('class', 'inherit_box').html(lang('advancedpop_move_to'));
            }
            
        },
        
        //显示进阶按钮
        showBtn: function(status) {
            if (status == 0) {//不可进阶
                this.heroSkillDom.find('#hero_advanced').attr('class', 'pop_btn_middle_disable btn').html(lang('not_reached'));
            } else if (status == 1){//可以进阶
                this.heroSkillDom.find('#hero_advanced').attr('class', 'pop_btn_middle btn').html(lang('advancedpop_advanced'));
            } else if (status == 2) {//已满级
                this.heroSkillDom.find('#hero_advanced').attr('class', 'pop_btn_middle_disable btn').html(lang('is_full_s'));
            }
        },
        
        bindAll: function(){
            var that = this;

            that.lbDom.undelegate('#inherit_btn', 'touchend').delegate('#inherit_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if (that.e.find('#advancedpop_wrap').hasClass('inherit')) {
                    that.e.find('#advancedpop_wrap').attr('class', 'content hero');
                    $(this).html(lang('advancedpop_advanced_inherit'));
                    that.scrollerRefresh();
                } else {
                    that.e.find('#advancedpop_wrap').attr('class', 'content inherit');
                    $(this).html(lang('advancedpop_hero_advanced'));
                }
                
            });
            
            that.topDom.find('#advancedpop_list').undelegate('li', 'click').delegate('li', 'click',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('selected')) {
                    return false;
                }
                that.getHeroData($(this).attr('heroid'));
            });
            
            that.lbDom.undelegate('.skill_img', 'touchend').delegate('.skill_img', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).attr('stype') == that.viewSkillId) {
                    return false;
                }
                that.getSkillData($(this));
            });
            
            that.heroSkillDom.undelegate('#skill_lvup', 'touchend').delegate('#skill_lvup', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.skillLevelUp();
            });
            
            that.heroSkillDom.undelegate('#hero_advanced.pop_btn_middle', 'touchend').delegate('#hero_advanced.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.confirm.show(lang('advancedpop_advanced'), lang('advancedpop_advanced_ask'), 2,
                function() {
                    xGame.confirm.hide();
                    that.heroAdvanced();
                },
                function() {
                    xGame.confirm.hide();
                });
                
            });
            
            that.heroInheritDom.undelegate('#advancedpop_info_btn', 'touchend').delegate('#advancedpop_info_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#advancedpop_info').css('display', 'block');
                
            });
            
            that.e.find('#advancedpop_info').undelegate('#advancedpop_info_hide', 'touchend').delegate('#advancedpop_info_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#advancedpop_info').css('display', 'none');
            });
            
            that.heroInheritDom.undelegate('#advancedpop_preview_btn.pop_btn_middle', 'touchend').delegate('#advancedpop_preview_btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.preview();
            });
            
            that.e.find('#advancedpop_preview').undelegate('#advancedpop_preview_hide', 'touchend').delegate('#advancedpop_preview_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#advancedpop_preview').css('display', 'none');
            });
            
            that.heroInheritDom.undelegate('#advancedpop_action_btn.pop_btn_middle', 'touchend').delegate('#advancedpop_action_btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.confirm.show(lang('advancedpop_inherit'), lang('advancedpop_inherit_ask'), 2,
                function() {
                    xGame.confirm.hide();
                    that.heroInherit();
                },
                function() {
                    xGame.confirm.hide();
                });
            });
            
            that.bindHero();
        },
        
        //获得武将信息
        getHeroData: function(gid) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) { 
                    case - 1 : xGame.toast.show(lang('get_hero_info_fail'));
                        break;
                    case - 2 : xGame.toast.show(lang('advancedpop_get_bd_skill_fail'));
                        break;
                    case - 3 : xGame.toast.show(lang('advancedpop_get_bd_skill_lvup_fail'));
                        break;
                    case - 4 : xGame.toast.show(lang('advancedpop_get_advanced_info_fail'));
                        break;
                    case - 5 : xGame.toast.show(lang('advancedpop_get_hero_equip_fail'));
                        break;
                    case - 6 : xGame.toast.show(lang('advancedpop_get_left_item_fail'));
                        break;
                    case 2:
                        xGame.toast.show(lang('lv_can_not_open_func'));
                        break;
                    case 1:
                        that.buildHero(res.general);
                        that.filleSkillInfo(res.skill_info);
                        that.filleSkillLevelUp(res.quality_info);
                        that.showBtn(res.level_up);
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'advanced',
                'm': 'get_general_info',
                'gid': gid
            },
            Func);

            Func = null;
        },
        
        //获得技能信息
        getSkillData: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) { 
                    case - 1 : xGame.toast.show(lang('parameter_error'));
                        break;
                    case - 2 : xGame.toast.show(lang('advancedpop_get_bd_skill_fail'));
                        break;
                    case - 3 : xGame.toast.show(lang('advancedpop_get_bd_skill_lvup_fail'));
                        break;
                    case 2:
                        xGame.toast.show(lang('lv_can_not_open_func'));
                        break;
                    case 1:
                        that.viewSkillId = res.info.type;
                        that.lbDom.find('.selected').removeClass('selected');
                        that.lbDom.find('#skill' + that.viewSkillId).addClass('selected');
                        that.filleSkillInfo(res.info);
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'advanced',
                'm': 'get_skill_info',
                'gid': that.viewGid,
                't': obj.attr('stype')
            },
            Func);

            Func = null;
        },
        
        //技能升级
        skillLevelUp: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) { 
                    case - 1 : xGame.toast.show(lang('parameter_error'));
                        break;
                    case - 2 : xGame.toast.show(lang('get_hero_info_fail'));
                        break;
                    case - 3 : xGame.toast.show(lang('advancedpop_get_bd_skill_fail'));
                        break;
                    case - 4 : xGame.toast.show(lang('advancedpop_skill_full'));
                        break;
                    case - 5 : xGame.toast.show(lang('advancedpop_get_bd_skill_lvup_fail'));
                        break;
                    case - 6 : xGame.toast.show(lang('advancedpop_tfcard_buzu'));
                        break;
                    case - 7 : xGame.toast.show(lang('level_up_fail'));
                        break;
                    case - 8 : xGame.toast.show(lang('advancedpop_cannot_lvup'));
                        break;
                    case 2:
                        xGame.toast.show(lang('lv_can_not_open_func'));
                        break;
                    case 1:
                        //更新武将的底
                        that.lbDom.find('#skill' + that.viewSkillId).attr('class', 'eid' + that.viewSkillId + ' skill_box q' + res.skill.quality + ' selected');
                        //更新顶部信息
                        that.filleSkillInfo(res.skill);
                        //升级特效
                        that.lvupAnim();
                        
                        //数值修改
                        that.showResource(res);
                        //只有当升级血时更新
                        if (that.viewSkillId == 4) {
                            that.lbDom.find('#hero_h').html(res.general.hp);
                        }
                        
                        //对勾改状态,未达成并且等级大于等于基本等级
                        var dom = that.heroSkillDom.find('#lvup_rule').find('#skill_need' + that.viewSkillId);
                        if (dom.find('span').hasClass('unreach') && res.skill.level >= that.need_level) {
                            dom.find('span').attr('class', 'reach').html('');
                        }
                        dom = null;
                        //更新进阶按钮
                        that.showBtn(res.level_up);
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'advanced',
                'm': 'level_up',
                'gid': that.viewGid,
                't': that.viewSkillId
            },
            Func);

            Func = null;
        },
        
        //武将进阶
        heroAdvanced: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) { 
                    case - 1 : xGame.toast.show(lang('parameter_error'));
                        break;
                    case - 2 : xGame.toast.show(lang('get_hero_info_fail'));
                        break;
                    case - 3 : xGame.toast.show(lang('advancedpop_get_bd_skill_fail'));
                        break;
                    case - 4 : xGame.toast.show(lang('advancedpop_skill_full'));
                        break;
                    case - 5 : xGame.toast.show(lang('advancedpop_get_bd_skill_lvup_fail'));
                        break;
                    case - 6 : xGame.toast.show(lang('advancedpop_rule_not_fit'));
                        break;
                    case - 7 : xGame.toast.show(lang('advancedpop_get_left_gem_fail'));
                        break;
                    case - 8 : xGame.toast.show(lang('advancedpop_gem2_buzu'));
                        break;
                    case - 9 : xGame.toast.show(lang('advancedpop_gem3_buzu'));
                        break;
                    case - 10 : xGame.toast.show(lang('advancedpop_osoul_buzu'));
                        break;
                    case - 11 : xGame.toast.show(lang('advancedpop_shengwang_buzu'));
                        break;
                    case 2:
                        xGame.toast.show(lang('lv_can_not_open_func'));
                        break;
                    case 1:
                        //顶部变颜色，两个顶部都得改
                        that.topDom.find('#advancedpop_list').find('#hero' + that.viewGid).attr('class', 'hero'  + res.general.quality + ' heroitem selected');
                        that.topDom.find('#advancedpop_eight_hero').find('#hero' + that.viewGid).attr('class', 'hero' + res.general.quality);
                        
                        //各种状态
                        that.buildHero(res.general);
                        that.filleSkillInfo(res.skill_info);
                        that.filleSkillLevelUp(res.quality_info);
                        that.showResource(res.resource);
                        that.showBtn(res.level_up);
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'advanced',
                'm': 'start_advanced',
                'gid': that.viewGid
            },
            Func);

            Func = null;
        },
        
        //技能升级动画
        lvupAnim: function() {
            var movieclip;

            //创建动画底图
            var mc = new movieClipClass({
                "img": ximg(staticUrl + 'img/pop/gemworld_lvup.png'),
                "width": 150,
                "height": 250,
                "x": -25,
                "y": -120,
                "loop": false,
                "endDestory": true,
                "frames": [{
                    rect: [0, 0],
                    interval: 40
                },
                {
                    rect: [150, 0],
                    interval: 40
                },
                {
                    rect: [300, 0],
                    interval: 40
                },
                {
                    rect: [450, 0],
                    interval: 40
                },
                {
                    rect: [6000, 0],
                    interval: 40
                }]
            });
            //开始播放
            mc.appendTo(this.heroSkillDom.find('#skill_box'));
            mc.play();
            mc = null;
        },
        
        //检查继承处两个按钮状态
        checkInheritBtn: function() {
            var quality1 = 0;
            if (this.gid1 != 0) {
                quality1 = parseInt(this.heroDataByGid[this.gid1].quality);
            }
            var quality2 = 0;
            if (this.gid2 != 0) {
                var quality2 = parseInt(this.heroDataByGid[this.gid2].quality);
            }
            if (quality1 > quality2 && quality1 > 0 && quality2 > 0) {
                this.heroInheritDom.find('#advancedpop_preview_btn').attr('class', 'btn pop_btn_middle');
                //当有cd时间的时候怎么按钮还是变灰
                if (CD['advanced'] != undefined && parseInt(CD['advanced']) > 0) {
                    this.heroInheritDom.find('#advancedpop_action_btn').attr('class', 'btn pop_btn_middle_disable');
                } else {
                    this.heroInheritDom.find('#advancedpop_action_btn').attr('class', 'btn pop_btn_middle');
                }
            } else {
                this.heroInheritDom.find('#advancedpop_preview_btn').attr('class', 'btn pop_btn_middle_disable');
                this.heroInheritDom.find('#advancedpop_action_btn').attr('class', 'btn pop_btn_middle_disable');
            }
            quality1 = null;
            quality2 = null;
        },
        
        /*这个功能的作用是传进参数，检测边界，
         * 返回true时代表有位置可以到达，再设置that.nearlyX和that.nearlyY完成操作；
         * 返回false时就代表直接返回原来的位置
         */
        checkPos: function(x, y) {
            var that = this;

            /*结果集*/
            var result = [];
            that.nearlyX = that.nearlyY = that.nearlyPosId = 0;

            /*第一步，告诉程序有几个点可以移动，不用排除自己*/
            ////console.log(that.canMove);
            for (var i = 9; i < 11; i++) {
                if (that.canMove['pos' + i] != undefined) {
                    result.push({
                        'name': 'pos' + i,
                        'id': that.boxPosList['pos' + i].id,
                        'distance': that.getDistance(Math.abs(that.boxPosList['pos' + i].left - x), Math.abs(that.boxPosList['pos' + i].top - y))
                    });
                }
            }
            /*按属性排大小，方法写在base.js里面*/
            result.sort(helper.compare("distance"));
            if (result.length != 0) {
                that.nearlyPosId = result[0]['id'];
                /*不管如何都返回最近的x，y*/
                that.nearlyX = that.boxPosList[result[0]['name']].left;
                that.nearlyY = that.boxPosList[result[0]['name']].top;

                var myDistance = that.getDistance(Math.abs(that.nearlyX - x), Math.abs(that.nearlyY - y));

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
        
        /*拖动主要逻辑*/
        bindHero: function() {
            var that = this;

            var ex = 0;
            var ey = 0;
            var ox = 0;
            var oy = 0;
            var target = null;

            that.moveDom.undelegate('span', 'touchstart').delegate('span', 'touchstart',
            function(event) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }

                that.touchGid = $(this).attr('gid');
                that.touchPos = $(this).attr('posid');
                that.touchHeroData = that.heroData[that.touchPos - 1];//这里需要减1

                /*复制一个元素占着那个位置*/
                if (that.touchPos < 9) {
                    that.topDom.find('ul').find('#hero' + that.touchGid).append('<img src="' + xGame.avatar(that.touchHeroData.image) + '" />');
                }

                ex = parseInt(that.heroPosList['pos' + that.touchPos].left);
                ey = parseInt(that.heroPosList['pos' + that.touchPos].top);

                ox = parseInt(event.touches[0].pageX);
                oy = parseInt(event.touches[0].pageY);

                $(this).addClass('ismoving');
                $(this).css('z-index', that.zIndex++);
                
                target = $(this);
            });

            that.e.unbind('touchmove').bind('touchmove',function(event) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
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

            /*这里必须区分*/
            that.moveDom.undelegate('span.ismoving', 'touchend').delegate('span.ismoving', 'touchend',
            function(event) {
                target = null;
                $(this).removeClass('ismoving');
                $(this).css('z-index', 'auto');
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var obj = $(this);
                var myx = parseInt(obj.css('left').replace('px', ''));
                var myy = parseInt(obj.css('top').replace('px', ''));

                /*如果坐标最后都没变化，就直接不管它,只删除后面的img*/
                if (myx == ex && myy == ey) {
                    that.topDom.find('ul').find('#hero' + that.touchGid).find('img').remove();
                    return false;
                }
                /*移动的是已在容器里的武将*/
                if (that.touchPos > 8) {
                    /*有位置可以移动，判断最近的位置*/
                    if (that.checkPos(myx, myy)) { //合法，到最近的位置上去
                        if (that.canMove['pos' + that.nearlyPosId] == that.touchGid) { //自己的范围内，返回自己范围
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease', null);
                        } else { //对面
                            //如果对面是空的，过去，
                            var duiId = that.touchPos == 9 ? 10 : 9;
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease',
                            function() {
                                //到位置上后再显示该显示的武将信息
                                obj.attr({
                                    'posid': duiId,
                                    'id': 'move_pos' + duiId
                                });
                            });
                            if (that.canMove['pos' + duiId] == 'block') {
                                that.canMove['pos' + that.touchPos] = 'block';
                            } else { //对面不是空的，交换
                                //先将对面的gid保存起来
                                var gid = that.canMove['pos' + duiId];
                                //console.log('对面的武将ID是' + gid);
                                that.moveDom.find('#move_pos' + duiId).animate({
                                    'left': that.heroPosList['pos' + that.touchPos].left + 'px',
                                    'top': that.heroPosList['pos' + that.touchPos].top + 'px'
                                },
                                100, 'ease',
                                function() {
                                    //到位置上后再显示该显示的武将信息
                                    $(this).attr({
                                        'posid': that.touchPos,
                                        'id': 'move_pos' + that.touchPos
                                    });
                                });
                                that.canMove['pos' + that.touchPos] = gid;
                            }
                            //设置参数
                            that.canMove['pos' + duiId] = that.touchGid;
                        }
                    } else { //不合法，直接消失
                        obj.remove();
                        that.canMove['pos' + that.touchPos] = 'block';
                    }
                } else { //移动的没有在容器里的武将
                    /*有位置可以移动，判断最近的位置*/
                    if (that.checkPos(myx, myy)) { //合法，到最近的位置上去
                        if (that.canMove['pos' + that.nearlyPosId] == that.touchGid) { //选择的英雄在容器内，先靠近，最后回到原处
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease',
                            function() {
                                obj.css({
                                    'left': that.heroPosList['pos' + that.touchPos].left + 'px',
                                    'top': that.heroPosList['pos' + that.touchPos].top + 'px'
                                });
                                that.topDom.find('ul').find('#hero' + that.touchGid).find('img').remove();
                            });
                            
                        } else { //选择的英雄不在容器内
                            //如果不是空位，删除原来空位元素，后面都一样：当前元素改成空位元素，新建一个初始元素，
                            if (that.canMove['pos' + that.nearlyPosId] != 'block') {
                                that.moveDom.find('#move_pos' + that.nearlyPosId).remove();
                            }
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease',
                            function() {
                                //到位置上后再显示该显示的武将信息
                                obj.attr({
                                    'posid': that.nearlyPosId,
                                    'id': 'move_pos' + that.nearlyPosId
                                });
                                that.topDom.find('ul').find('#hero' + that.touchGid).find('img').remove();
                            });

                            that.moveDom.append('<span posid="' + that.touchPos + '" gid="' + that.touchHeroData.id + '" id="move_pos' + that.touchPos + '" style="background-image:url(' + xGame.avatar(that.touchHeroData.image) + ');' + that.heroPosList['pos' + that.touchPos].style + '"></span>');

                            //设置数据
                            that.canMove['pos' + that.nearlyPosId] = that.touchHeroData.id;

                            var duiId = that.nearlyPosId == 9 ? 10 : 9;
                            if (that.canMove['pos' + duiId] == that.touchGid) {
                                that.canMove['pos' + duiId] = 'block';
                                that.moveDom.find('#move_pos' + duiId).remove();
                            }
                            duiId = null;
                        }
                    } else { //不合法
                        //位置没到位,回到原处，显示上是消失了
                        obj.css({
                            'left': that.heroPosList['pos' + that.touchPos].left + 'px',
                            'top': that.heroPosList['pos' + that.touchPos].top + 'px'
                        });
                        that.topDom.find('ul').find('#hero' + that.touchGid).find('img').remove();
                    }
                }
                
                //9号位发生变化时触发
                if (that.canMove['pos9'] != that.gid1 && that.canMove['pos9'] != 'block' && that.canMove['pos9'] != 0) {
                    that.getHeroData(that.canMove['pos9']);
                }
                
                that.gid1 = that.canMove['pos9'] == 'block' ? 0 : that.canMove['pos9'];
                that.gid2 = that.canMove['pos10'] == 'block' ? 0 : that.canMove['pos10'];

                that.setInheritBoxBg();
                that.checkInheritBtn();
            });
        },
        
        //进阶传承
        heroInherit: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                if (res.status == 1) {
                    //动画,结束后更新数据,unlock特殊处理
                    that.inheritAnim(res);
                } else {
                    switch (parseInt(res.status)) { 
                        case - 1 : xGame.toast.show(lang('parameter_error'));
                            break;
                        case - 2 : xGame.toast.show(lang('advancedpop_at_least_30_day'));
                            break;
                        case - 3 : xGame.toast.show(lang('get_hero_info_fail'));
                            break;
                        case - 4 : xGame.toast.show(lang('advancedpop_get_bd_skill_fail'));
                            break;
                        case - 5 : xGame.toast.show(lang('advancedpop_nodata_inherit'));
                            break;
                        case - 6 : xGame.toast.show(lang('advancedpop_no_one_inherit'));
                            break;
                        case 2:
                            xGame.toast.show(lang('lv_can_not_open_func'));
                            break;
                        default:
                            xGame.toast.show(lang('undefined_error'));
                            break;
                    }
                    xGame.unlock();
                }
            };
            loadContents({
                'c': 'advanced',
                'm': 'advanced_inherit',
                'gid1': that.gid1,
                'gid2': that.gid2
            },
            Func);

            Func = null;
        },
        
        inheritAnim: function(res) {
            xGame.islock = true;
            var that = this;
            var obj = that.moveDom.find('#move_pos9').clone().attr('id', 'temp');
            obj.appendTo(that.moveDom).addClass('inherit_anim').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
            function() {
                obj.remove();
                obj = null;
                that.shineAnim();
                
                that.moveDom.find('#move_pos10').addClass('tada').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
                function() {
                    $(this).removeClass('tada');
                    
                    //本地数据更新，不需要更新资源字段,res.list特殊处理下
                    for (var i = 0; i < 8; i++) {
                        if (res.list[i] != undefined) {
                            that.heroDataByGid[res.list[i].id] = res.list[i];
                        }
                    }
                    //修改两个dom的颜色
                    that.topDom.find('#advancedpop_eight_hero').find('#hero' + that.gid1).attr('class', 'hero' + that.heroDataByGid[that.gid1].quality);
                    that.topDom.find('#advancedpop_eight_hero').find('#hero' + that.gid2).attr('class', 'hero' + that.heroDataByGid[that.gid2].quality);
                    
                    that.topDom.find('#advancedpop_list').find('#hero' + that.gid1).attr('class', 'heroitem selected hero' + that.heroDataByGid[that.gid1].quality);
                    that.topDom.find('#advancedpop_list').find('#hero' + that.gid2).attr('class', 'heroitem hero' + that.heroDataByGid[that.gid2].quality);
                    
                    that.buildHero(res.general);
                    that.filleSkillInfo(res.skill_info);
                    that.filleSkillLevelUp(res.quality_info);
                    that.showBtn(res.level_up);
                    xGame.updateCd('advanced', res.cd);
                    that.setInheritBoxBg();
                    
                    that = null;
                    xGame.unlock();
                });
            });
        },

        shineAnim: function() {
            var movieclip;

            //创建动画底图
            var mc = new movieClipClass({
                "img": staticUrl + 'img/pop/inheritpop_nim.png',
                "width": 256,
                "height": 150,
                "x": 660,
                "y": 195,
                "loop": false,
                "endDestory": true,
                "frames": [{
                    rect: [0, 0],
                    interval: 50
                },
                {
                    rect: [256, 0],
                    interval: 50
                },
                {
                    rect: [512, 0],
                    interval: 50
                },
                {
                    rect: [768, 0],
                    interval: 50
                },
                {
                    rect: [0, 150],
                    interval: 50
                },
                {
                    rect: [256, 150],
                    interval: 50
                },
                {
                    rect: [512, 150],
                    interval: 50
                },
                {
                    rect: [768, 150],
                    interval: 50
                },
                {
                    rect: [0, 300],
                    interval: 50
                },
                {
                    rect: [256, 300],
                    interval: 50
                }]
            });
            //开始播放
            mc.appendTo(this.moveDom);
            mc.play();
            mc = null;
        },
        
        //查看可进阶数据
        preview: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) { 
                    case - 1 : xGame.toast.show(lang('parameter_error'));
                        break;
                    case - 2 : xGame.toast.show(lang('get_hero_info_fail'));
                        break;
                    case - 3 : xGame.toast.show(lang('advancedpop_get_bd_skill_fail'));
                        break;
                    case 2:
                        xGame.toast.show(lang('lv_can_not_open_func'));
                        break;
                    case 1:
                        that.buildPreview(res.advanced);
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'advanced',
                'm': 'get_advanced_inherit_info',
                'gid': that.gid1
            },
            Func);

            Func = null;
        },
        
        //构建预览数据
        buildPreview: function(data) {
            var html = '';
            html += this.returnLi(lang('advancedpop_o_wuli'), data.wuli);
            html += this.returnLi(data.skill1_addname, data.skill1_value);
            html += this.returnLi(lang('advancedpop_o_zhili'), data.zhili);
            html += this.returnLi(data.skill2_addname, data.skill2_value);
            html += this.returnLi(lang('advancedpop_o_tili'), data.tili);
            html += this.returnLi(data.skill3_addname, data.skill3_value);
            html += this.returnLi(lang('advancedpop_o_hp'), data.hp);
            html += this.returnLi(data.skill4_addname, data.skill4_value);
            this.e.find('#advancedpop_preview').find('ul').html(html);
            html = null;
            this.e.find('#advancedpop_preview').css('display', 'block');
        },
        
        returnLi: function(text, num) {
            return '<li>' + text + '：<em>+' + num + '</em></li>';
        },

        unbindAll: function () {
            if (this.lbDom) {
                this.lbDom.undelegate('#inherit_btn', 'touchend');
                this.lbDom.undelegate('.skill_img', 'touchend');
                this.lbDom.remove();
                this.lbDom = null;
            }
            if (this.topDom) {
                this.topDom.find('#advancedpop_list').undelegate('li', 'click');
                this.topDom.remove();
                this.topDom = null;
            }
            if (this.heroSkillDom) {
                this.heroSkillDom.undelegate('#skill_lvup', 'touchend');
                this.heroSkillDom.undelegate('#hero_advanced.pop_btn_middle', 'touchend');
                this.heroSkillDom.remove();
                this.heroSkillDom = null;
            }
            if (this.heroInheritDom) {
                this.heroInheritDom.undelegate('#advancedpop_info_btn', 'touchend');
                this.heroInheritDom.undelegate('#advancedpop_action_btn.pop_btn_middle', 'touchend');
                this.heroInheritDom.undelegate('#advancedpop_preview_btn.pop_btn_middle', 'touchend');
                this.heroInheritDom.remove();
                this.heroInheritDom = null;
            }
            if (this.e) {
                this.e.find('#advancedpop_info').undelegate('#advancedpop_info_hide', 'touchend');
                this.e.find('#advancedpop_preview').undelegate('#advancedpop_preview_hide', 'touchend');
                this.e.find('.content').find('#advancedpop_hide').unbind('touchend');
                this.e.unbind('touchmove');
                this.e.remove();
                this.e = null;
            }
            if (this.moveDom) {
                this.moveDom.undelegate('span', 'touchstart')
                this.moveDom.undelegate('span.ismoving', 'touchend')
                this.moveDom.find('#move_pos9').attr('id', 'temp').removeClass('inherit_anim').unbind('webkitAnimationEnd');
                this.moveDom.find('#move_pos10').removeClass('tada').unbind('webkitAnimationEnd');
                this.moveDom.remove();
                this.moveDom = null;
            }
        },
        
        hide: function() {
            xGame.unlock();
            this.unbindAll();
            xGame.updateCd('advanced', 0);
            this.isshow = false;
            this.heroSkillLevelData = null;
            this.viewGid = null;
            this.viewSkillId = null;
            this.need_level = null;
            
            this.gid1 = null;
            this.gid2 = null;
            this.heroData = null;
            this.heroDataByGid = null;
            this.touchHeroData = null;
            this.ruleData = null;

            this.touchGid = null;
            this.touchPos = null;
            this.canMove = null;
            this.heroPosList = null;
            this.boxPosList = null;
            this.nearlyX = null;
            this.nearlyY = null;
            this.nearlyPosId = null;
            this.zIndex = null;
            this.distance = null;
            
            if (this.heroScroller != null) {
                this.heroScroller.destroy();
                delete this.heroScroller;
            }
        },
    };
})();