// 铜雀台
(function() {
    beautyPopClass = function () {
        this.e = null;
        this.isshow = false;
        this.beautyScroller = null;
        this.leftDom = null; // 界面的左侧部分
        this.mainDom = null; // 中间的碎片界面
        this.skillDom = null; // 界面右侧的技能部分
        this.interactDom = null; // 互动界面
        this.advanceDom = null; // 进阶界面
        this.listDom = null; // 美人头像列表
        this.activateNum = 0; // 激活人数
        this.nowBeautyId = 0; // 当前美人Id
        this.infoDom = null; // 玩法说明
        this.interactShow = false; // 是否显示互动界面
        this.advanceShow = false; // 是否显示进阶界面
        this.robChip = null; // 抢夺碎片界面
        this.greetTime = 0; // 问候次数
        this.avoidWarCost = 0; // 免战花费
        this.avoidWarTime = 0; // 免战次数
        this.giveFlowerCost = 0; // 送礼花费
        this.roseNum = 0; // 拥有的玫瑰花数
        this.rewardTimer = null; // 互动奖励掉落计时器
        this.robChip = null; // 抢夺碎片界面
        this.robTime = 0; // 抢夺次数
        this.robBuyTime = 0; // 购买次数
        this.activateMaxNum = 0; // 激活人数上限
        this.buyRoseCost = 0; // 买花花费
        this.presentDom = null; // 赠礼界面
        this.beautyNameArr = []; // 美人名字
        this.presentInfo = null; // 赠礼界面信息
        this.diamondNum = 0; // 拥有的宝石数量
        this.favourPoint = 0; // 好感度
        this.interactHasData = false; // 互动界面是否有数据
        this.skillInfo = null; // 技能信息
        this.beautyLevel = 0; // 美人等级
        this.clockDom = null; // 美人升级时钟
        this.levelUpAnim = null; // 升级动画
        this.levelUpTimer = null; // 升级动画计时器
        this.robCost = null; // 购买抢夺次数花费
        this.robChipLeft = null; // 抢夺碎片界面左侧
        this.robChipRight = null; // 抢夺碎片界面右侧
        this.robChipList = null; // 抢夺碎片列表
        this.nowChipId = 0; // 当前抢夺碎片ID
        this.skillRewardNum = 0; // 特殊技能的奖励数量
        this.levelUping = false; // 是否正在升级中
        this.sayDom = null; // 美人私语
        this.sayTimer = null; // 美人私语计时器
        this.robLogDom = null; // 抢夺记录
        this.robScroller = null;
        this.avoiding = false; // 是否正在免战
        this.levelUpRefreshTimer = null; // 升级刷新计时器
    };

    beautyPopClass.prototype = {
        init: function () {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var render = function (res) {

                if (res.status == 1) {
                    that.initPage(res);
                } else {
                    xGame.toast.show(res.msg);
                    $('#worldloading').hide();
                }
            };

            loadContents({
                'c': 'beauty',
                'm': 'index'
            }, render);
        },

        initPage: function (res) {
            
            var that = this;
            that.e = xGame.loadPopTmpl('beauty');
            that.leftDom = that.e.find('#beauty_left');
            that.mainDom = that.e.find('#beauty_main');
            that.skillDom = that.e.find('#beauty_skill');
            that.interactDom = that.e.find('#beauty_interact');
            that.advanceDom = that.e.find('#beauty_advance');
            that.listDom = that.leftDom.find('#beauty_list');
            that.chipDom = that.mainDom.find('#beauty_chip');
            that.infoDom = that.e.find('#beauty_info');
            that.presentDom = that.e.find('#beauty_present_pop');
            that.clockDom = that.mainDom.find('#beauty_clock');
            that.sayDom = that.mainDom.find('#beauty_say');

            that.e.find('#beauty_hidebtn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            that.showMainDom(res.beauty_info);
            that.showlist(res.beauty_info.active);

            var item = that.listDom.find('.avatar');
            var height = item.height() + parseInt(item.css('margin-top')) + parseInt(item.css('margin-bottom'));
            item = null;

            that.beautyScroller = new iScroll('beauty_scroller', {
                bounce: false,
                hScrollbar: false,
                vScrollbar: false
            });

            that.beautyScroller.scrollTo(0, -height * (that.nowBeautyId - 1));

            that.beautySay(res.say);
            that.bind();
            that.e.show();
            that.isshow = true;
            $('#worldloading').hide();
        },

        bind: function () {
            
            var that = this;

            // 美人列表
            that.listDom.delegate('.avatar', 'click', function (e) {
                
                if (this.id == that.nowBeautyId) {
                    return false;
                }

                if (xGame.checkLock()) {
                     return false;
                }

                var $this = $(this);
                $this.addClass('selected').siblings().removeClass('selected');
                that.getBeautyData(this.id);
                $this = null;
                e.preventDefault();
            });

            // 玩法说明按钮
            that.leftDom.find('#beauty_info_btn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.infoDom.show();
                e.preventDefault();
            });

            // 玩法说明的关闭按钮
            that.infoDom.find('#beauty_info_hidebtn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.infoDom.hide();
                e.preventDefault();
            });

            // 玩法说明的遮罩
            that.infoDom.find('.mask').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.infoDom.hide();
                e.preventDefault();
            });

            // 互动按钮
            that.mainDom.find('#beauty_interact_btn').bind('touchend', function (e) {

                if (that.levelUping) {
                    xGame.toast.show(lang('beautypop_leveluping'));
                    return false;
                }

                if (xGame.checkLock()) {
                    return false;
                }

                if (that.interactShow) {
                    that.skillDom.show();
                    that.interactToggle();
                    xGame.doAnimate(that.interactDom, 'fadeOutLeft', '0.5s', function () {
                        xGame.unlock();
                    });

                    if (that.rewardTimer != null) {
                        that.interactDom.find('.reward_box').remove();
                        clearTimeout(that.rewardTimer);
                        that.rewardTimer = null;
                    }
                } else {

                    if (that.interactHasData) { // 如果之前已经打开过互动界面
                        that.skillDom.hide();
                        that.interactToggle();
                        that.advanceToggle(true);
                        xGame.doAnimate(that.interactDom, 'fadeInLeft', '0.5s', function () {
                            xGame.unlock();
                        });
                    } else {
                        that.getInteractData(that.nowBeautyId);
                    }
                }

                e.preventDefault();
            });

            // 问候、亲近、宠幸按钮
            that.interactDom.delegate('.pop_btn_small', 'touchend', function(e) {
                
                if (that.levelUping) {
                    xGame.toast.show(lang('beautypop_leveluping'));
                    return false;
                }

                if (xGame.checkLock()) {
                    return false;
                }

                that.startInteract(that.nowBeautyId, that.interactDom.find('.interact_btn').index($(this)) + 1);
                e.preventDefault();
            });

            // 激活按钮
            that.mainDom.delegate('#beauty_activate_btn.activate', 'touchend', function(e) {
                
                if (that.levelUping) {
                    xGame.toast.show(lang('beautypop_leveluping'));
                    return false;
                }
                
                if (that.activateNum >= that.activateMaxNum) {
                    xGame.toast.show(lang('beautypop_dont_activate'));
                    return false;
                }

                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.confirm.show(lang('beautypop_activate'), lang('beautypop_activate_content'), 2, function () {
                    xGame.confirm.hide();
                    that.activateBeauty(that.nowBeautyId);
                }, function () {
                    xGame.confirm.hide();
                });

                e.preventDefault();
            });

            // 休息按钮
            that.mainDom.delegate('#beauty_activate_btn.rest', 'touchend', function(e) {
                
                if (that.levelUping) {
                    xGame.toast.show(lang('beautypop_leveluping'));
                    return false;
                }

                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.confirm.show(lang('beautypop_rest'), lang('beautypop_rest_content'), 2, function () {
                    xGame.confirm.hide();
                    that.restBeauty(that.nowBeautyId);
                }, function () {
                    xGame.confirm.hide();
                });

                e.preventDefault();
            });

            // 点碎片框到抢夺界面
            that.mainDom.delegate('.chip_item', 'touchend', function (e) {
                
                if (that.levelUping) {
                    xGame.toast.show(lang('beautypop_leveluping'));
                    return false;
                }

                if ($(this).hasClass('has')) {
                    return false;
                }

                if (xGame.checkLock()) {
                    return false;
                }

                that.nowChipId = $(this).attr('chipid');
                that.loadRobChip(that.nowBeautyId, that.nowChipId);
                e.preventDefault();
                e.stopPropagation();
            });

            // 免战
            that.mainDom.delegate('#beauty_avoid_war.pop_btn_smaller', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.confirm.show(lang('beautypop_avoid_war'), lang('beautypop_avoid_war_content', [that.avoidWarCost, that.avoidWarTime]), 2, function () {
                    xGame.confirm.e.removeClass('beauty_confirm_hint');
                    xGame.confirm.hide();
                    that.avoidWar();
                }, function () {
                    xGame.confirm.e.removeClass('beauty_avoid_war');
                    xGame.confirm.hide();
                });

                xGame.confirm.e.addClass('beauty_confirm_hint');
                e.preventDefault();
            });

            // 赠礼
            that.skillDom.delegate('#beauty_give_present.pop_btn_small', 'touchend', function(e) {
                
                if (that.levelUping) {
                    xGame.toast.show(lang('beautypop_leveluping'));
                    return false;
                }

                if (that.favourPoint >= 100) {
                    xGame.toast.show(lang('beautypop_favour'));
                    return false;
                }

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.buildPresent();
                e.preventDefault();
            });

            // 送花
            that.skillDom.delegate('#beauty_give_flower.pop_btn_small', 'touchend', function(e) {
                
                if (that.levelUping) {
                    xGame.toast.show(lang('beautypop_leveluping'));
                    return false;
                }

                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.confirm.show(lang('beautypop_give_flower'), lang('beautypop_give_flower_content', [that.giveFlowerCost]), 2, function () {
                    xGame.confirm.e.removeClass('beauty_confirm_hint');
                    xGame.confirm.hide();
                    that.giveFlower(that.nowBeautyId);
                }, function () {
                    xGame.confirm.e.removeClass('beauty_confirm_hint');
                    xGame.confirm.hide();
                });

                xGame.confirm.e.addClass('beauty_confirm_hint');
                e.preventDefault();
            });

            // 普通技能
            that.skillDom.delegate('.common_skill_item', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                $(this).addClass('selected').siblings().removeClass('selected');
                that.skillDom.find('.common_skill_info').hide();
                that.skillDom.find('.common_skill_info').eq($(this).index()).show();
                e.preventDefault();
            });

            // 购买玫瑰花
            that.skillDom.find('#beauty_flower_add').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.confirm.show(lang('beautypop_buy_rose'), lang('beautypop_buy_rose_content', [that.buyRoseCost, that.roseNum]), 2, function () {
                    that.buyRose();
                }, function () {
                    xGame.confirm.hide();
                });

                e.preventDefault();
            });

            // 赠礼界面的关闭按钮
            that.presentDom.find('#beauty_present_hidebtn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.presentDom.hide();
                e.preventDefault();
            });

            // 选择赠礼方式
            that.presentDom.delegate('.addtype', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                $(this).addClass('selected').siblings().removeClass('selected');
                e.preventDefault();
            });

            // 赠礼界面的赠礼按钮
            that.presentDom.find('.pop_btn_middle').bind('touchend', function (e) {
                
                var dom = that.presentDom.find('.addtype.selected');

                if (dom.length == 0) {
                    xGame.toast.show(lang('beautypop_present_selected'));
                    dom = null;
                    return false;
                }

                if (xGame.checkLock()) {
                    return false;
                }

                that.givePresent(that.nowBeautyId, dom.attr('type'));
                dom = null;
                e.preventDefault();
            });

            // 升级进阶
            that.mainDom.delegate('#beauty_levelup_btn.pop_btn_small', 'touchend', function (e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                if ($(this).hasClass('levelup')) { // 升级

                    if (that.chipDom.find('.chip_item.has').length < 6) {
                        xGame.toast.show(lang('beautypop_chip_not_enough'));
                        return false;
                    }

                    if (that.interactShow) {
                        that.mainDom.find('#beauty_interact_btn').trigger('touchend');
                    }
                    
                    xGame.confirm.show(lang('level_up'), lang('beautypop_levelup_content'), 2, function () {
                        xGame.confirm.hide();
                        that.levelUp(that.nowBeautyId, 1);
                    }, function () {
                        xGame.confirm.hide();
                    });
                    xGame.confirm.e.addClass('beauty_levelup');
                } else if ($(this).hasClass('advance')) { // 进阶

                    if (that.advanceShow) {
                        xGame.islock = true;
                        that.skillDom.show();
                        that.advanceToggle();
                        xGame.doAnimate(that.advanceDom, 'fadeOutLeft', '0.5s', function () {
                            xGame.unlock();
                        });
                    } else {
                        that.getAdvanceData(that.nowBeautyId);
                    }
                } else if ($(this).hasClass('levelup_cancel')) { // 取消升级
                    xGame.confirm.show(lang('beautypop_levelup_cancel'), lang('beautypop_levelup_cancel_content'), 2, function () {
                        that.cancelLevelUp(that.nowBeautyId);
                        xGame.confirm.hide();
                    }, function () {
                        xGame.confirm.hide();
                    });
                }

                e.preventDefault();
            });

            // 领取特殊技能奖励
            that.skillDom.delegate('#beauty_skill_reward.pop_btn_small', 'touchend', function (e) {

                if (that.levelUping) {
                    xGame.toast.show(lang('beautypop_leveluping'));
                    return false;
                }

                if (xGame.checkLock()) {
                    return false;
                }

                that.getSkillReward(that.nowBeautyId);
                e.preventDefault();
            });

            // 美人进阶
            that.advanceDom.find('#beauty_advance_btn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.confirm.show(lang('advancedpop_advanced'), lang('beautypop_advance_content'), 2, function () {
                    xGame.confirm.hide();
                    that.advanceBeauty(that.nowBeautyId);
                }, function () {
                    xGame.confirm.hide();
                });

                e.preventDefault();
            });

            //点美人说话
            that.chipDom.bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                if (e.target.id == 'beauty_chip') {
                    that.touchBeautySay(that.nowBeautyId);
                }

                e.preventDefault();
            });

            // 互动界面的返回按钮
            that.interactDom.find('#beauty_interact_hidebtn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.mainDom.find('#beauty_interact_btn').trigger('touchend');
                e.preventDefault();
            });

            // 进阶界面的返回按钮
            that.advanceDom.find('#beauty_advance_hidebtn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.mainDom.find('#beauty_levelup_btn.pop_btn_small').trigger('touchend');
                e.preventDefault();
            });

            // 主界面的碎片转化按钮
            that.mainDom.find('#beauty_convert_btn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.confirm.show(lang('beautypop_convert_title'), lang('beautypop_convert_content'), 2, function () {
                    that.convertChip(that.nowBeautyId);
                    xGame.confirm.hide();
                }, function () {
                    xGame.confirm.hide();
                });

                e.preventDefault();
            });
        },

        // 构建美人列表
        showlist: function (list) {
            
            var html = '';
            var item = null;
            this.beautyNameArr = {};
            this.activateNum = 0;

            for (var i = 0, len = list.length; i < len; i++) {
                item = list[i];
                html += '<li class="avatar" id="'+ item.beauty_id +'">';
                html += '<span class="img" style="background-image: url('+ staticUrl + 'img/pop/beautypop_avatar' + item.beauty_id + '.png' +')"></span>';

                // 是否激活
                if (parseInt(item.active) == 1) {
                    html += '<span class="activate"></span>';
                    this.activateNum ++;
                } else {
                    html += '<span class="activate" style="display:none"></span>';
                }

                html += '<span class="name" style="color:'+ helper.getItemColor(item.quality) +'">'+ item.name +'</span>';
                this.beautyNameArr[item.beauty_id] = item.name;
            }

            this.listDom.html(html);
            this.listDom.find('.avatar').eq(parseInt(this.nowBeautyId) - 1).addClass('selected');
            this.leftDom.find('.has_num').html(this.activateNum);
        },

        // 构建美人主界面
        showMainDom: function (info) {
            var that = this;
            info = info || {};
            info.info = info.info || { beauty_id: 1, sub_rose: 5, max_num: 1, buy_rose: 20, beauty_level: 1, battle_cd: 0 };
            info.battle = info.battle || { gold: 50, times: 0 };
            info.gift_info = info.gift_info || [];

            that.nowBeautyId = info.info.beauty_id;
            that.avoidWarCost = info.battle.gold;
            that.avoidWarTime = info.battle.times;
            that.giveFlowerCost = info.info.sub_rose;
            that.activateMaxNum = info.info.max_num;
            that.buyRoseCost = info.info.buy_rose;
            that.presentInfo = info.gift_info;
            that.beautyLevel = info.info.beauty_level;

            // 隐藏互动和进阶界面
            that.skillDom.show();
            that.interactToggle(true);
            that.advanceToggle(true);

            // 激活人数上限
            that.leftDom.find('.max_num').html(that.activateMaxNum);

            // 免战CD
            if (parseInt(info.info.battle_cd) <= 0) {
                that.mainDom.find('.avoid_time').hide();
                that.avoiding = false;
            } else {
                that.mainDom.find('.avoid_time').show();
                xGame.updateCd('beautyAvoid', info.info.battle_cd);
                that.avoiding = true;
            }

            // 是否激活
            if (parseInt(info.info.beauty_active) == 1) {
                that.mainDom.find('#beauty_activate_btn').html(lang('beautypop_rest')).addClass('rest').removeClass('activate');
            } else {
                that.mainDom.find('#beauty_activate_btn').html(lang('beautypop_activate')).addClass('activate').removeClass('rest');
            }

            // 是否可激活
            if (parseInt(info.info.is_active) == 1) {
                that.mainDom.find('#beauty_activate_btn').removeClass('pop_btn_smaller_disable').addClass('pop_btn_smaller');
            } else {
                that.mainDom.find('#beauty_activate_btn').removeClass('pop_btn_smaller').addClass('pop_btn_smaller_disable');
            }

            // 技能生效时间
            if (parseInt(info.info.skill_cd) <= 0) {
                that.skillDom.find('.skill_time').hide();
                that.skillDom.find('.skill_no_open').show();
            } else {
                that.skillDom.find('.skill_time').show();
                xGame.updateCd('beautySkill', info.info.skill_cd);
                that.skillDom.find('.skill_no_open').hide();
            }

            // 升级、进阶、满级
            switch (parseInt(info.info.up_status)) {
                case 1:
                    that.mainDom.find('#beauty_levelup_btn').html(lang('level_up')).addClass('levelup pop_btn_small').removeClass('advance levelup_cancel pop_btn_small_disable');
                    break;
                case 2:
                    that.mainDom.find('#beauty_levelup_btn').html(lang('advancedpop_advanced')).addClass('advance pop_btn_small').removeClass('levelup levelup_cancel pop_btn_small_disable');
                    break;
                case 3:
                    that.mainDom.find('#beauty_levelup_btn').html(lang('equiprefinepop_full')).removeClass('pop_btn_small advance levelup_cancel levelup').addClass('pop_btn_small_disable');
                    break;
                default:
                    break;
            }

            // 设置碎片转化按钮状态
            if (parseInt(info.info.conversion_status) == 1) {
                that.mainDom.find('#beauty_convert_btn').show();
            } else {
                that.mainDom.find('#beauty_convert_btn').hide();
            }

            that.mainDom.find('#beauty_level').html('Lv.' + that.beautyLevel);
            that.mainDom.find('#beauty_quality').attr('class' ,'beauty_quality q' + info.info.beauty_quality);
            that.mainDom.find('#beauty_pic').css('background-image', 'url(' + ximg(staticUrl + 'img/pop/beautypop_girl'+ that.nowBeautyId +'.png') + ')');
            that.buildChip(info.chip);
            that.buildSkill(info.skill);
            that.buildSpecialSkill(info.special_skill);
            that.buildFavour(info.happy);
            that.handleBeautyLevelUp(info.info.up_cd, info.info.up_succ);
        },

        // 美人私语
        beautySay: function (msg) {
            var that = this;

            if (that.sayTimer) {
                clearTimeout(that.sayTimer);
            }

            that.sayTimer = setTimeout(function () {
                that.sayDom.show();
                that.sayDom.fadeIn(500).children('.say_content').html(msg);
                that.sayTimer = setTimeout(function () {
                    that.sayDom.fadeOut();
                    that.sayTimer = null;
                }, 2000);
            }, 1000);
        },

        // 构建碎片
        buildChip: function (chip) {
            
            var that = this;
            var dom = that.chipDom.children('.chip_item');
            var id = 0;
            chip = chip || [];

            dom.each(function (i) {
                var $this = $(this);
                if (!chip[i]) {
                    $this.removeClass('has');
                    $this.find('.img').hide();
                    $this.find('.no_chip').show();
                    return;
                }
                id = chip[i].chip_id;
                $this.attr('chipid', id);

                if (parseInt(chip[i].chip) > 0) {
                    $this.addClass('has');
                    $this.find('.img').css('background-image', 'url(' + staticUrl + 'img/pop/beautypop_girl'+ that.nowBeautyId +'_chip'+ id +'.png' + ')').show();
                    $this.find('.num').html('x' + chip[i].chip);
                    $this.find('.name').html(chip[i].chip_name);
                    $this.find('.no_chip').hide();
                } else {
                    $this.removeClass('has');
                    $this.find('.img').hide();
                    $this.find('.no_chip').show();
                }

                $this = null;
            });
        },

        // 构建碎片单元
        buildChipItem: function (beauty_id, chip_id, chip_num, chip_name) {
            
            var chipDom = this.chipDom.find('.chip_item').eq(parseInt(chip_id) - 1);
            chipDom.addClass('has');
            chipDom.find('.img').css('background-image', 'url(' + staticUrl + 'img/pop/beautypop_girl'+ beauty_id +'_chip'+ chip_id +'.png' + ')').show();
            chipDom.find('.num').html('x' + chip_num);
            chipDom.find('.name').html(chip_name);
            chipDom.find('.no_chip').hide();
            chipDom = null;
        },

        // 构建普通技能
        buildSkill: function (skill) {
            
            skill = skill || [];
            this.skillInfo = skill;
            var itemDom = this.skillDom.find('.common_skill_item');
            var groupDom = this.skillDom.find('.common_skill_group');
            var dom = null;
            var html = '';

            for (var i = 0, len = skill.length; i < len; i++) {
                dom = itemDom.eq(i);
                dom.children('.img').attr('class', 'img skill' + skill[i].id);

                if (parseInt(skill[i].lock) == 0) {
                    dom.children('.lock').show();
                    dom.children('.msg').html(lang('beautypop_unlock', [skill[i].lock_msg])).show();
                } else {
                    dom.children('.lock').hide();
                    dom.children('.msg').html('').hide();
                }

                html += '<li class="common_skill_info">';

                if (parseInt(skill[i].next_value) > 0) {
                    html += '<p class="info">' + skill[i].name + '：' + skill[i].content + ' ' + this.getWan(skill[i].value) + '</p>';
                    html += '<p class="info next">' + lang('beautypop_next_lv') + skill[i].content + ' ' + this.getWan(skill[i].next_value) + '</p>';
                } else {
                    html += '<p class="info max">' + skill[i].name + '：' + skill[i].content + ' ' + this.getWan(skill[i].value) + '</p>';
                }

                html += '</li>';
            }

            groupDom.html(html);
            groupDom.children().first().show();
            itemDom.removeClass('selected').first().addClass('selected');
        },

        // 构建特殊技能
        buildSpecialSkill: function (skill) {
            skill = skill || { id: 13, num: 0, skill_name: '天香绝技', lock: 1 };
            this.skillRewardNum = skill.num || 0;
            this.skillDom.find('.special_skill_img').attr('class', 'special_skill_img skill' + (skill.id || 13));
            this.skillDom.find('.special_skill_name').html(skill.skill_name || '天香绝技');
            this.skillDom.find('#beauty_receive_number').html(this.getWan(this.skillRewardNum));

            if (parseInt(this.skillRewardNum) <= 0) {
                this.skillDom.find('#beauty_skill_reward').removeClass('pop_btn_small').addClass('pop_btn_small_disable');
            } else {
                this.skillDom.find('#beauty_skill_reward').removeClass('pop_btn_small_disable').addClass('pop_btn_small');
            }

            if (parseInt(skill.lock) == 0) {
                this.skillDom.find('.special_skill_lock').show();
            } else {
                this.skillDom.find('.special_skill_lock').hide();
            }
        },

        // 构建好感度
        buildFavour: function (happy) {
            this.roseNum = happy.rose;
            this.diamondNum = happy.diamond;
            this.favourPoint = happy.ratio;
            this.skillDom.find('#beauty_favour_level').css('background-image', 'url(' + staticUrl + 'img/pop/beautypop_favour_level' + happy.quality + '.png' + ')');
            this.skillDom.find('#beauty_addition_percent').html(happy.skill + '%');
            this.skillDom.find('#beauty_flower_num').html(this.roseNum);
            this.skillDom.find('#beauty_now_progress').width(this.favourPoint + '%');
            this.skillDom.find('#beauty_progress_text').html(lang('beautypop_now_favour', [this.favourPoint]));

            if (this.favourPoint > 0 && this.favourPoint < 100) {
                this.skillDom.find('#beauty_favour_progress').find('.light').show();
            } else {
                this.skillDom.find('#beauty_favour_progress').find('.light').hide();
            }
        },

        // 构建赠礼界面
        buildPresent: function () {
            var html = '';
            var item = null;
            this.presentDom.find('.now_beauty').html(this.beautyNameArr[this.nowBeautyId]);
            this.presentDom.find('.now_favour').html(this.favourPoint);
            this.presentDom.find('.now_diamond').html(this.diamondNum);

            for (var i = 0, len = this.presentInfo.length; i < len; i++) {
                item = this.presentInfo[i];
                html += '<li class="addtype" type="'+ item.type +'">';
                html += '<em class="pigeon"></em>';

                if (i == 0) {
                    html += '<span class="price">' + lang('beautypop_present1', [item.gold]) + '</span>';
                } else {
                    html += '<span class="price">' + lang('beautypop_present2', [item.diamond]) + '</span>';
                }

                html += '<span class="favour">' + lang('beautypop_present_favour', [item.ratio]) + '</span>';
            }

            this.presentDom.find('#beauty_present_list').html(html);
            this.presentDom.show();
        },

        // 刚进入界面时处理美人是否升级，success：0，无升级操作；1，成功升级；2，升级失败
        handleBeautyLevelUp: function (cd, success) {
            
            if (parseInt(cd) > 0) {
                this.levelUping = true;
                this.clockDom.show();
                this.runClock(cd);
                xGame.updateCd('beautyLevelUp', cd);
                this.refreshLevelUp();
                this.mainDom.find('#beauty_levelup_btn').html(lang('beautypop_levelup_cancel')).removeClass('levelup advance pop_btn_small_disable').addClass('levelup_cancel pop_btn_small');
            } else {
                this.levelUping = false;
                this.clockDom.hide();

                if (CD.beautyLevelUp) {
                    CD.beautyLevelUp.clear();
                    this.refreshLevelUp(true);
                }

                if (parseInt(success) == 1) {
                    this.levelUpLight();
                    this.sendLevelUpMsg(this.nowBeautyId);
                } else if (parseInt(success) == 2) {
                    xGame.toast.show(lang('beautypop_levelup_fail'));
                    this.sendLevelUpMsg(this.nowBeautyId);
                }

            }
        },

        // 告诉后端已播放完动画
        sendLevelUpMsg: function (beautyId) {
            loadContents({
                'c': 'beauty',
                'm': 'level_up_status',
                'beauty_id': beautyId
            });
        },

        // 切换互动界面状态
        interactToggle: function (toggleStatus) {

            if (toggleStatus != null) {
                this.interactShow = toggleStatus;
            }
            
            if (this.interactShow) {
                this.interactShow = false;
                this.interactDom.hide();
                this.mainDom.find('#beauty_interact_btn').html(lang('beautypop_interact_btn'));
            } else {
                this.interactShow = true;
                this.interactDom.show();
                this.mainDom.find('#beauty_interact_btn').html(lang('infopop_hide'));
            }

            if (this.interactShow || this.advanceShow) {
                this.e.find('#beauty_hidebtn').hide();
            } else {
                this.e.find('#beauty_hidebtn').show();
            }
        },

        // 切换进阶界面状态
        advanceToggle: function (toggleStatus) {

            if (toggleStatus != null) {
                this.advanceShow = toggleStatus;
            }
            
            if (this.advanceShow) {
                this.advanceShow = false;
                this.advanceDom.hide();
                this.mainDom.find('#beauty_levelup_btn.advance').html(lang('advancedpop_advanced'));
                this.e.find('#beauty_hidebtn').show();
            } else {
                this.advanceShow = true;
                this.advanceDom.show();
                this.mainDom.find('#beauty_levelup_btn.advance').html(lang('infopop_hide'));
                this.e.find('#beauty_hidebtn').hide();
            }

            if (this.interactShow || this.advanceShow) {
                this.e.find('#beauty_hidebtn').hide();
            } else {
                this.e.find('#beauty_hidebtn').show();
            }
        },

        // 获取美人数据
        getBeautyData: function (id) {
            
            var that = this;

            var render = function (res) {
                if (parseInt(res.status) == 1) {
                    that.showMainDom(res.beauty_info);
                    that.beautySay(res.say);
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'get_beauty_info',
                'beauty_id': id
            }, render);
        },

        // 激活美人
        activateBeauty: function (id) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.mainDom.find('#beauty_activate_btn').html(lang('beautypop_rest')).addClass('rest').removeClass('activate');
                    that.listDom.find('.activate').eq(parseInt(id) - 1).show();
                    that.activateNum = parseInt(res.wakeup_num);
                    that.leftDom.find('.has_num').html(that.activateNum);
                    that.beautySay(res.say);
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'wakeup_beauty',
                'beauty_id': id
            }, render);
        },

        // 美人休息
        restBeauty: function (beautyId) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.restHandle(beautyId, res.wakeup_num);
                    that.beautySay(res.say);
                    that.buildSpecialSkill(res.special_skill);

                    player.updateinfo({
                        'gold': res.all_gold,
                        'silver': res.all_silver
                    });
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'rest_beauty',
                'beauty_id': beautyId
            }, render);
        },

        // 让美人进入休息状态
        restHandle: function (beautyId, wakeupNum) {
            this.mainDom.find('#beauty_activate_btn').html(lang('beautypop_activate')).addClass('activate').removeClass('rest');
            this.listDom.find('.activate').eq(parseInt(beautyId) - 1).hide();
            this.activateNum = parseInt(wakeupNum);
            this.leftDom.find('.has_num').html(this.activateNum);
            this.skillDom.find('.skill_time').hide();
            this.skillDom.find('.skill_no_open').show();
            xGame.updateCd('beautySkill', 0);
        },

        // 免战
        avoidWar: function () {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.avoiding = true;
                    that.avoidWarTime = res.times;
                    that.mainDom.find('.avoid_time').show();
                    xGame.updateCd('beautyAvoid', res.cd_time);
                    player.updateinfo({
                        'gold': res.all_gold
                    });
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'peace'
            }, render);
        },

        // 获取互动数据
        getInteractData: function (id) {
            
            var that = this;

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.buildInteractDom(res);
                } else {
                    xGame.toast.show(res.msg);
                    xGame.unlock();
                }
            };

            loadContents({
                'c': 'beauty',
                'm': 'active_index',
                'id': id
            }, render);
        },

        // 构建互动界面
        buildInteractDom: function (info) {
            var that = this;
            var greetDom = that.interactDom.find('#beauty_interact_greet');
            var closeDom = that.interactDom.find('#beauty_interact_close');
            var loveDom = that.interactDom.find('#beauty_interact_love');
            that.greetTime = info.greeting;
            that.interactHasData = true;

            that.buildInteractItem(greetDom, 'beautyInteractGreet', info.g_dateline, info.greeting, info.spend_greet); // 问候
            that.buildInteractItem(closeDom, '', 0, info.close, info.spend_close); // 亲近
            that.buildInteractItem(loveDom, '', 0, info.love, info.spend_love); // 宠幸

            // VIP等级不足
            if (parseInt(info.three) == 0) {
                loveDom.find('.interact_btn').removeClass('pop_btn_small').addClass('pop_btn_small_disable');
            } else {
                loveDom.find('.vip').hide();
            }

            that.skillDom.hide();
            that.interactToggle();
            that.advanceToggle(true);
            xGame.doAnimate(that.interactDom, 'fadeInLeft', '0.5s', function () {
                xGame.unlock();
            });

            greetDom = null;
            closeDom = null;
            loveDom = null;
        },

        // 构建互动单元
        buildInteractItem: function (dom, cdId, cd, num, cost) {

            if (parseInt(cd) > 0) {
                xGame.updateCd(cdId, cd);
                dom.find('.colling_time').show();
                dom.find('.interact_btn').removeClass('pop_btn_small').addClass('pop_btn_small_disable');
            } else {
                dom.find('.colling_time').hide();

                if (parseInt(num) > 0) {
                    dom.find('.interact_btn').removeClass('pop_btn_small_disable').addClass('pop_btn_small');
                } else {
                    dom.find('.interact_btn').removeClass('pop_btn_small').addClass('pop_btn_small_disable');
                }
            }

            dom.find('.times').html(num);
            dom.find('.cost').html('x' + cost);
        },

        // 检测互动界面问候按钮状态
        checkInteractBtn: function () {

            var greetDom = this.interactDom.find('#beauty_interact_greet');

            if (this.greetTime > 0) {
                greetDom.find('.interact_btn').removeClass('pop_btn_small_disable').addClass('pop_btn_small');
            }

            greetDom = null;
        },

        // 开始互动
        startInteract: function (id, type) {
            
            var that = this;

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.interactRewardDown(res, type);
                    that.beautySay(res.say);
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'active_action',
                'beauty_id': id,
                'type': type
            }, render);
        },

        // 互动奖励
        interactRewardDown: function (data, type) {
            
            var that = this;

            player.updateinfo({
                'gold': data.gold,
                'silver': data.silver
            });

            // 显示倒计时
            if (parseInt(data.cd_time) > 0) {
                xGame.updateCd('beautyInteractGreet', data.cd_time);
                that.interactDom.find('#beauty_interact_greet').find('.colling_time').show();
                that.interactDom.find('#beauty_interact_greet').find('.interact_btn').removeClass('pop_btn_small').addClass('pop_btn_small_disable');
                that.greetTime = data.lastnum;
            }

            // 刷新好感度
            that.buildFavour(data.happy);
            // 刷新特殊技能
            that.buildSpecialSkill(data.special_skill);
            // 更新剩余次数
            that.interactDom.find('.times').eq(type - 1).html(data.lastnum);
            // 更新钻石数量
            that.diamondNum = data.diamond;

            if (parseInt(data.lastnum) <= 0) {
                that.interactDom.find('.interact_btn').eq(type - 1).removeClass('pop_btn_small').addClass('pop_btn_small_disable');
            }

            if (that.interactDom.find('.reward_box').length > 0) {
                that.interactDom.find('.reward_box').remove();
            }

            if (that.rewardTimer) {
                clearTimeout(that.rewardTimer);
            }

            that.interactDom.append(that.buildRewardBox(data.reward));

            that.rewardTimer = setTimeout(function () {
                
                if (that.interactDom) {
                    var dom = that.interactDom.find('.reward_box');

                    if (dom.length > 0) {
                        dom.remove();
                        that.rewardTimer = null;
                    }

                    dom = null;
                }
            }, 3000);
        },

        // 构建互动奖励弹窗
        buildRewardBox: function (rewardData) {

            var html = '<div class="reward_box" id="beauty_reward_box">';
            var item = null;
            var type = 0;

            for (var i = 0, len = rewardData.length; i < len; i++) {
                item = rewardData[i];
                type = parseInt(item.type);

                switch (type) {
                    case 2: // 银币
                    case 3: // 黄宝石
                    case 4: // 紫宝石
                    case 5: // 原魂
                    case 6: // 玉石
                    case 31: // 玫瑰花
                    case 32: // 钻石
                        html += '<span class="reward_down pos' + (i + 1) + ' type' + type + '"><em class="pic"></em>x' + item.num + '</span>';
                        break;
                    case 30: // 美人碎片
                        html += '<span class="reward_down pos' + (i + 1) + ' type' + type + '"><em class="pic" style="background-image: url('+ staticUrl + 'img/pop/beautypop_girl' + item.beauty_id + '_chip' + item.chip_id + '.png)"></em>' + item.name + '</span>';

                        // 如果获得的是当前美人的碎片，则立刻显示
                        if (item.beauty_id == this.nowBeautyId) {
                            this.buildChipItem(item.beauty_id, item.chip_id, item.num, item.name);
                        }
                        break;
                    default :
                        break;
                }
            }

            html += '</div>';

            item = null;
            type = null;
            return html;
        },

        // 赠礼
        givePresent: function (id, type) {
            
            var that = this;

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    player.updateinfo({
                        'gold': res.gold
                    });
                    that.diamondNum = res.diamond_num;
                    that.buildFavour(res.happy);
                    that.presentDom.hide();
                    that.beautySay(res.say);
                    that.buildSpecialSkill(res.special_skill);
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            }

            loadContents({
                'c': 'beauty',
                'm': 'gift',
                'beauty_id': id,
                'type': type
            }, render);
        },

        // 送花
        giveFlower: function (id) {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                xGame.unlock();
                if (parseInt(res.status) == 1) {
                    that.roseNum = res.rose;
                    that.skillDom.find('#beauty_flower_num').html(that.roseNum);
                    that.beautySay(res.say);

                    if (parseInt(res.skill_cd) > 0) {
                        that.skillDom.find('.skill_time').show();
                        xGame.updateCd('beautySkill', res.skill_cd);
                        that.skillDom.find('.skill_no_open').hide();
                    } else {
                        that.skillDom.find('.skill_time').hide();
                        that.skillDom.find('.skill_no_open').show();
                    }
                } else if (parseInt(res.status) == 2) {
                    that.skillDom.find('#beauty_flower_add').trigger('touchend');
                } else {
                    xGame.toast.show(res.msg);
                }

            };

            loadContents({
                'c': 'beauty',
                'm': 'give_flower',
                'beauty_id': id
            }, render);

        },

        // 购买玫瑰花
        buyRose: function () {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.roseNum = res.rose;
                    that.skillDom.find('#beauty_flower_num').html(that.roseNum);
                    player.updateinfo({
                        'gold': res.gold
                    });
                    xGame.confirm.show(lang('beautypop_buy_rose'), lang('beautypop_buy_rose_content', [that.buyRoseCost, that.roseNum]), 2, function () {
                        that.buyRose();
                    }, function () {
                        xGame.confirm.hide();
                    });
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'buy_rose',
            }, render);
        },

        // 领取特殊技能奖励
        getSkillReward: function (id) {
            
            var that = this;

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    xGame.toast.show(lang('reward_success'));
                    that.skillRewardNum = 0;
                    that.skillDom.find('#beauty_receive_number').html(that.skillRewardNum);
                    that.skillDom.find('#beauty_skill_reward').removeClass('pop_btn_small').addClass('pop_btn_small_disable');

                    player.updateinfo({
                        'gold': res.all_gold,
                        'silver': res.all_silver
                    });
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'action_special_reward',
                'beauty_id': id
            }, render);
        },

        // 升级，type为1时是用户的操作，为2时是每5分钟刷一次接口
        levelUp: function (beautyId, type) {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.clockDom.show();
                    that.levelUping = true;
                    that.runClock(res.cd);

                    if (type == 1) {
                        that.restHandle(beautyId, res.wakeup_num);
                        xGame.updateCd('beautyLevelUp', res.cd);
                        that.refreshLevelUp();
                        that.mainDom.find('#beauty_levelup_btn').html(lang('beautypop_levelup_cancel')).removeClass('levelup').addClass('levelup_cancel');
                        that.buildSpecialSkill(res.special_skill);

                        player.updateinfo({
                            'gold': res.all_gold,
                            'silver': res.all_silver
                        });
                    } else if (type == 2) {
                        if (parseInt(res.cd) > 0) {
                            xGame.updateCd('beautyLevelUp', res.cd);
                        } else { // 升级成功
                            that.levelUpLight();
                            that.sendLevelUpMsg(that.nowBeautyId);
                            that.showMainDom(res.beauty_info);
                            that.refreshLevelUp(true);
                        }
                    }
                } else if (parseInt(res.status) == -4 && type == 2) { // 升级失败，碎片被抢
                    xGame.toast.show(res.msg);

                    if (CD.beautyLevelUp) {
                        CD.beautyLevelUp.clear();
                        that.refreshLevelUp(true);
                    }
                    
                    that.clockDom.hide();
                    that.sendLevelUpMsg(that.nowBeautyId);
                    that.levelUping = false;
                    that.buildChip(res.chip);
                    that.mainDom.find('#beauty_levelup_btn').html(lang('level_up')).removeClass('levelup_cancel').addClass('levelup');
                } else {
                    xGame.toast.show(res.msg);
                    that.clockDom.hide();
                    that.levelUping = false;
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'level_up',
                'beauty_id': beautyId,
                'type': type
            }, render, (type == 2 ? true : false));
        },

        // 刷新升级接口
        refreshLevelUp: function (isClear) {
            
            var that = this;

            if (that.levelUpRefreshTimer) {
                clearTimeout(that.levelUpRefreshTimer);
                that.levelUpRefreshTimer = null;
            }

            if (isClear) {
                return false;
            }

            that.levelUpRefreshTimer = setTimeout(function () {
                that.levelUp(that.nowBeautyId, 2);
                that.refreshLevelUp();
            }, 300000);
        },

        // 显示升级时钟
        runClock: function (cd) {
            
            var ratio = (3600 - parseInt(cd)) / 3600;

            if (ratio <= 0.5) {
                this.clockDom.find('#beauty_clock_left_up').hide();
                this.clockDom.find('#beauty_clock_right_up').css('z-index', '1').show();
                this.clockDom.find('#beauty_clock_down').css('-webkit-transform', 'rotate('+ (ratio * 360) +'deg)');
            } else {
                this.clockDom.find('#beauty_clock_left_up').show();
                this.clockDom.find('#beauty_clock_right_up').css('z-index', '3').show();
                this.clockDom.find('#beauty_clock_down').css('-webkit-transform', 'rotate('+ (ratio * 360) +'deg)');
            }
        },

        // 升级动画
        levelUpLight: function () {

            var that = this;
            var time = 100;
            that.clockDom.hide();

            that.levelUpAnim = new movieClipClass({
                'img': 'static/img/pop/beautypop_levelup_anim.png',
                'width': 500,
                'height': 400,
                'x': -50,
                'y': 50,
                'loop': false,
                'endDestory': true,
                'zindex': 10,
                'frames': [
                    {
                        rect: [0, 0],
                        interval: time,
                    },
                    {
                        rect: [500, 0],
                        interval: time,
                    },
                    {
                        rect: [1000, 0],
                        interval: time,
                    },
                    {
                        rect: [1500, 0],
                        interval: time,
                    },
                    {
                        rect: [0, 400],
                        interval: time,
                    },
                    {
                        rect: [500, 400],
                        interval: time,
                    },
                    {
                        rect: [1000, 400],
                        interval: time,
                    },
                    {
                        rect: [1500, 400],
                        interval: time,
                    },
                    {
                        rect: [0, 800],
                        interval: time,
                    },
                    {
                        rect: [500, 800],
                        interval: time,
                    },
                    {
                        rect: [1000, 800],
                        interval: time,
                    },
                    {
                        rect: [1500, 800],
                        interval: time,
                    },
                    {
                        rect: [0, 1200],
                        interval: time,
                    },
                    {
                        rect: [500, 1200],
                        interval: time,
                    },
                    {
                        rect: [1000, 1200],
                        interval: time,
                    },
                    {
                        rect: [1500, 1200],
                        interval: time,
                    },
                ],
                'callback': function () {
                    that.levelUpSuccess(1);
                },
            });

            that.levelUpAnim.appendTo(that.mainDom);
            that.levelUpAnim.play();
        },

        // 升级成功
        levelUpSuccess: function (type) {
            
            var that = this;

            if (that.levelUpTimer) {
                clearTimeout(that.levelUpTimer);
            }

            var resultDom = that.e.find('.beauty_result_wrap');

            if (resultDom.length > 0) {
                resultDom.remove();
            }

            resultDom = $('<div class="beauty_result_wrap"></div>');

            var levelUpAnim = new movieClipClass({
                "img": staticUrl + 'img/pop/strongpop_result_anim.png',
                "width": 300,
                "height": 220,
                "x": 176,
                "y": -50,
                "loop": false,
                "callback": function () {
                    that.levelUpTimer = setTimeout(function () {
                        resultDom.fadeOut('normal', function() {
                            resultDom.remove();
                            resultDom = null;
                        });
                    }, 1000);
                },
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

            levelUpAnim.appendTo(resultDom);
            resultDom.append('<span class="type'+ type +'"></span>');
            that.e.find('#beautypop_wrap').append(resultDom);
            levelUpAnim.play();
        },

        // 取消升级
        cancelLevelUp: function (beautyId) {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.levelUping = false;
                    that.clockDom.hide();
                    CD.beautyLevelUp.clear();
                    that.refreshLevelUp(true);
                    that.mainDom.find('#beauty_levelup_btn').html(lang('level_up')).removeClass('levelup_cancel').addClass('levelup');
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'cancel_levelup',
                'beauty_id': beautyId
            }, render);
        },

        // 获取进阶信息
        getAdvanceData: function (beautyId) {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.buildAdvanceDom(res);
                } else {
                    xGame.toast.show(res.msg);
                    xGame.unlock();
                }
            };

            loadContents({
                'c': 'beauty',
                'm': 'advance_index',
                'beauty_id': beautyId
            }, render);
        },

        // 构建进阶界面
        buildAdvanceDom: function (res) {
            
            var that = this;
            that.advanceResource(res.list);
            that.advanceAdd(res.add_info);
            that.advanceHas(res.info);
            that.skillDom.hide();
            that.advanceToggle(false);
            that.interactToggle(true);
            xGame.doAnimate(that.advanceDom, 'fadeInLeft', '0.5s', function () {
                xGame.unlock();
            });
        },

        // 进阶消耗的资源
        advanceResource: function (list) {
            
            var type = 0;
            var value = 0;
            var html = '';

            for (var i = 1; i <= 6; i++) {
                type = list['type' + i];
                value = list['value' + i];

                if (parseInt(value) <= 0) {
                    continue;
                } else {
                    html += '<li class="resource type'+ type +'">x' + this.getWan(value) + '</li>';
                }
            }

            this.advanceDom.find('#beauty_advance_need').html(html);
        },

        // 进阶加成
        advanceAdd: function (info) {
            
            var html = '';

            for (var i = 0, len = info.length; i < len; i++) {
                html += '<li class="add">' + info[i].name + '<em class="value"> ' + (parseInt(info[i].value) > 0 ? '+' + this.getWan(info[i].value) : '') + '</em></li>';
            }

            this.advanceDom.find('#beauty_advance_add').html(html);
        },

        // 玩家拥有的资源
        advanceHas: function (list) {
            
            var html = '';
            var type = 0;

            for (var i = 1; i <= 6; i++) {
                type = list['type' + i];
                html += '<li class="resource type'+ type +'">x' + this.getWan(list['value' + i]) + '</li>';
            }

            this.advanceDom.find('#beauty_resource').html(html);
        },

        getWan: function(num) {

            var value = num;
            var number = parseInt(value);

            if (number >= 100000) {
                return Math.floor(parseInt(number) / 10000) + lang('10k');
            } else {
                return value;
            }
        },

        // 进阶美人
        advanceBeauty: function (beautyId) {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.skillDom.show();
                    that.levelUpSuccess(2);
                    that.restHandle(beautyId, res.wakeup_num);
                    that.showMainDom(res.beauty_info);
                    that.listDom.find('.name').eq(parseInt(that.nowBeautyId) - 1).css('color', helper.getItemColor(res.beauty_info.info.beauty_quality));
                    xGame.doAnimate(that.advanceDom, 'fadeOutLeft', '0.5s', function () {
                        that.advanceDom.hide();
                        that.advanceShow = false;
                        xGame.unlock();
                    });

                    player.updateinfo({
                        'gold': res.all_gold,
                        'silver': res.all_silver
                    });
                } else {
                    xGame.toast.show(res.msg);
                    xGame.unlock();
                }
            };

            loadContents({
                'c': 'beauty',
                'm': 'advance_action',
                'beauty_id': beautyId
            }, render);
        },

        // 点美人说话
        touchBeautySay: function (beautyId) {
        
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.beautySay(res.say);
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'get_say_info',
                'beauty_id': beautyId
            }, render, true);
        },

        // 碎片转化
        convertChip: function (id) {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {

                if (parseInt(res.status) == 1) {
                    player.updateinfo({
                        'gold': res.gold,
                        'silver': res.silver
                    });
                    that.diamondNum = res.diamond;
                    that.convertRewardDown(res.reward);
                    that.buildFavour(res.happy);
                    that.buildSpecialSkill(res.special_skill);
                    that.buildChip(res.chip);
                } else {
                    xGame.toast.show(res.msg);
                    xGame.unlock();
                }
            };

            loadContents({
                'c': 'beauty',
                'm': 'conversion_chip',
                'beauty_id': id,
            }, render);
        },

        // 掉落转换碎片的奖励
        convertRewardDown: function (rewardData) {

            var that = this;

            if (that.e.find('.reward_box').length > 0) {
                that.e.find('.reward_box').remove();
            }

            if (that.rewardTimer) {
                clearTimeout(that.rewardTimer);
            }

            that.mainDom.append(that.buildRewardBox(rewardData));

            that.rewardTimer = setTimeout(function () {
                
                if (that.mainDom) {
                    var dom = that.mainDom.find('.reward_box');

                    if (dom.length > 0) {
                        dom.remove();
                        that.rewardTimer = null;
                    }

                    dom = null;
                }

                xGame.unlock();
            }, 3000);
        },

        // 获取抢夺碎片界面数据
        loadRobChip: function (beautyId, chipId) {
            
            var that = this;

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.buildRobChipDom(res);
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'loot_index',
                'beauty_id': beautyId,
                'chip_id': chipId
            }, render);
        },

        // 构建抢夺碎片界面
        buildRobChipDom: function (res) {

            var that = this;
            that.robChip = xGame.loadPopTmpl('robchip');
            that.robChipLeft = that.robChip.find('#robchip_left');
            that.robChipRight = that.robChip.find('#robchip_right');
            that.robChipList = that.robChipLeft.find('#robchip_list');
            that.robLogDom = that.robChipRight.find('#robchip_log');

            that.robChip.find('#robchip_hidebtn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.robChipHide();
                e.preventDefault();
            });

            that.robChipInitPage(res);
            that.robChipBind();
            that.robChip.show();
        },

        // 抢夺碎片界面初始化
        robChipInitPage: function (res) {
            this.robTime = res.free_times;
            this.robBuyTime = res.vip_times;
            this.robCost = res.price;
            this.robChip.find('#robchip_left_time').html(this.robTime);

            if (parseInt(res.cd) > 0) {
                this.robChip.find('#robchip_refresh_btn').removeClass('pop_btn_small').addClass('pop_btn_small_disable');
                xGame.updateCd('beautyRefresh', res.cd);
            } else {
                this.robChip.find('#robchip_refresh_btn').removeClass('pop_btn_small_disable').addClass('pop_btn_small').html(lang('beautypop_chip_refresh'));
            }

            this.buildRobChipList(res.loot_list);
            this.buildRobLog(res.loot_log);

            this.robScroller = new iScroll('robchip_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            this.robScroller.refresh();
        },

        // 构建抢夺列表
        buildRobChipList: function (list) {
            
            var html = '';
            var item = null;

            for (var i = 0, len = list.length; i < len; i++) {
                item = list[i];
                html += '<li class="player">';
                html += '<div class="player_info gender'+ item.gender +'" uid="'+ item.uid +'">';
                html += '<div class="player_des">';
                html += '<p class="text"><span class="level">Lv.'+ item.level +'</span><span class="name">'+ item.nickname +'</span></p>';
                html += '<p class="text">'+ lang('beautypop_chip_country') +'<span class="country">'+ (item.country.length > 0 ? item.country : lang('no')) +'</span></p>';
                html += '<p class="text probability" style="background-position:0 '+ (-(parseInt(item.rate_info) - 1) * 26) +'px"></p>';
                html += '</div></div>';
                html += '<button class="btn pop_btn_small robchip_btn" uid="'+ item.uid +'">'+ lang('beautypop_chip_btn') +'</button>';
                html += '</li>';
            }

            this.robChipList.html(html);
            item = null;
        },

        // 构建抢夺记录
        buildRobLog: function (list) {
            
            var html = '';
            var item = null;

            for (var i = 0, len = list.length; i < len; i++) {
                item = list[i];

                html += '<li class="log">';

                if (parseInt(item.battle_status) == 1) {
                    html += '<span class="avatar type1"></span>';
                } else {
                    html += '<span class="avatar type2"></span>';
                }

                html += '<div class="log_info">';
                html += '<span class="time">'+ item.dateline +'</span>';
                html += '<span class="des">' + lang((parseInt(item.battle_status) == 1 ? 'beautypop_roblog_win' : 'beautypop_roblog_lose'), ['<em class="player" uid="'+ item.to_uid +'">' + item.to_name + '</em>', item.chip_name]) + '<em class="viewbattle" viewid="'+ item.report_id +'">[' + lang('battle_report') + ']</em></span>';
                html += '</div></li>';
            }

            this.robLogDom.html(html);
        },

        robChipBind: function () {
            
            var that = this;

            // 刷新对手
            that.robChipLeft.delegate('#robchip_refresh_btn.pop_btn_small', 'touchend', function (e) {

                if (xGame.checkLock()) {
                    return false;
                }

                that.refreshRobChipList(that.nowBeautyId, that.nowChipId);
                e.preventDefault();
            });

            // 购买抢夺次数
            that.robChipLeft.find('#robchip_buy_btn').bind('touchend', function (e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.confirm.show(lang('beautypop_chip_buy_times'), lang('beautypop_chip_buy_times_content', [that.robCost, that.robBuyTime, that.robTime]), 2, function () {
                    that.buyRobTimes();
                }, function () {
                    xGame.confirm.hide();
                });

                e.preventDefault();
            });

            // 抢夺按钮
            that.robChipLeft.delegate('.robchip_btn', 'touchend', function(e) {
                
                if (parseInt(that.robTime) <= 0) {
                    if (parseInt(that.robBuyTime) > 0) {
                        that.robChip.find('#robchip_buy_btn').trigger('touchend');
                    } else {
                        xGame.toast.show(lang('beautypop_chip_no_times'));
                    }
                    return false;
                }

                var $this = $(this);

                if (that.avoiding) {
                    xGame.confirm.show(lang('beautypop_chip_btn'), lang('beautypop_chip_avoidwar'), 2, function () {
                        xGame.confirm.hide();
                        that.robChipBattle(that.nowBeautyId, that.nowChipId, $this.attr('uid'));
                    }, function () {
                        xGame.confirm.hide();
                    });
                } else {
                    that.robChipBattle(that.nowBeautyId, that.nowChipId, $this.attr('uid'));
                }

                e.preventDefault();
            });

            // 查看玩家信息
            that.robLogDom.delegate('.player', 'click', function(e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.showpop('info', $(this).attr('uid'));
                e.preventDefault();
            });

            // 查看战报
            that.robLogDom.delegate('.viewbattle', 'click', function(e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                miscpop.viewBattleFromMail($(this).attr('viewid'), 'beauty');
                e.preventDefault();
            });

            // 抢夺列表查看玩家信息
            that.robChipList.delegate('.player_info', 'touchend', function(e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.showpop('info', $(this).attr('uid'));
                e.preventDefault();
            });
        },

        // 刷新抢夺列表
        refreshRobChipList: function (beautyId, chipId) {
            
            var that = this;

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.buildRobChipList(res.loot_list);
                    that.robChip.find('#robchip_refresh_btn').removeClass('pop_btn_small').addClass('pop_btn_small_disable');
                    xGame.updateCd('beautyRefresh', res.cd);
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'loot_reflush',
                'beauty_id': beautyId,
                'chip_id': chipId
            }, render);
        },

        // 购买抢夺次数
        buyRobTimes: function () {
            
            var that = this;

            if (parseInt(that.robBuyTime) <= 0) {
                xGame.toast.show(lang('overseastradepop_err2'));
                return false;
            }

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.robTime = res.free_times;
                    that.robBuyTime = res.vip_times;
                    that.robChip.find('#robchip_left_time').html(that.robTime);
                    player.updateinfo({
                        'gold': res.gold
                    });

                    xGame.confirm.show(lang('beautypop_chip_buy_times'), lang('beautypop_chip_buy_times_content', [that.robCost, that.robBuyTime, that.robTime]), 2, function () {
                        that.buyRobTimes();
                    }, function () {
                        xGame.confirm.hide();
                    });

                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'beauty',
                'm': 'buy_loot_times'
            }, render);
        },

        // 抢夺碎片
        robChipBattle: function (beautyId, chipId, uid) {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                xGame.unlock();

                if (parseInt(res.status) == 1) {

                    if (that.avoiding) {
                        that.avoiding = false;
                        xGame.updateCd('beautyAvoid', 0);
                    }

                    miscpop.enterBattleFromBeauty(res);
                    that.robTime = res.free_times;
                    that.robBuyTime = res.vip_times;
                    that.robChip.find('#robchip_left_time').html(that.robTime);
                    that.buildChip(res.chip);
                    that.buildRobLog(res.loot_log);
                    that.refreshResource(res.reward_info);

                    // 隐藏铜雀台界面
                    that.e.hide();

                    // 获得碎片
                    if (parseInt(res.b_info.chip_status) == 1) {
                        that.robChipHide();
                    } else {
                        that.robChip.hide();
                    }

                    // 如果进阶界面已经打开，则刷新进阶界面
                    if (that.advanceShow) {
                        that.getAdvanceData(that.nowBeautyId);
                    }
                } else {
                    xGame.toast.show(res.msg);
                }
            };

            loadContents({
                'c': 'beauty',
                'm': 'loot_action',
                'beauty_id': beautyId,
                'chip_id': chipId,
                't': uid
            }, render);
        },

        // 刷新玩家拥有的物资
        refreshResource: function (info) {

            var type = parseInt(info.type);

            switch (type) {
                case 31: // 玫瑰花
                    this.roseNum = parseInt(this.roseNum) + parseInt(info.value);
                    this.skillDom.find('#beauty_flower_num').html(this.roseNum);
                    break;
                case 32: // 钻石
                    this.diamondNum = parseInt(this.diamondNum) + parseInt(info.value);
                    break;
                default :
                    break;
            }
        },

        robChipUnbind: function () {
            this.robChip.find('#robchip_hidebtn').unbind('touchend');
            this.robChipLeft.find('#robchip_refresh_btn').unbind('touchend');
            this.robChipLeft.find('#robchip_buy_btn').unbind('touchend');
            this.robChipLeft.undelegate('.robchip_btn', 'touchend');
            this.robLogDom.undelegate('.player', 'click');
            this.robLogDom.undelegate('.viewbattle', 'click');
            this.robChipList.undelegate('.player_info', 'touchend');
        },

        robChipHide: function () {
            
            xGame.unlock();
            xGame.updateCd('beautyRefresh', 0);
            this.robChipUnbind();

            if (this.robScroller) {
                this.robScroller.destroy();
                this.robScroller = null;
            }

            this.robCost = null; // 购买抢夺次数花费
            this.robChipLeft = null; // 抢夺碎片界面左侧
            this.robChipRight = null; // 抢夺碎片界面右侧
            this.robChipList = null; // 抢夺碎片列表
            this.robLogDom = null; // 抢夺记录
            this.robChip.remove();
            this.robChip = null;
        },

        unbind: function () {
            this.e.find('#beauty_hidebtn').unbind('touchend');
            this.listDom.undelegate('.avatar', 'click');
            this.leftDom.find('#beauty_info_btn').unbind('touchend');
            this.infoDom.find('#beauty_info_hidebtn').unbind('touchend');
            this.infoDom.find('.mask').unbind();
            this.mainDom.find('#beauty_interact_btn').unbind('touchend');
            this.mainDom.undelegate('#beauty_activate_btn.activate', 'touchend');
            this.mainDom.undelegate('#beauty_activate_btn.rest', 'touchend');
            this.mainDom.undelegate('.chip_item', 'touchend');
            this.mainDom.undelegate('#beauty_avoid_war.pop_btn_smaller', 'touchend');
            this.skillDom.undelegate('#beauty_give_present.pop_btn_small', 'touchend');
            this.skillDom.undelegate('#beauty_give_flower.pop_btn_small', 'touchend');
            this.skillDom.undelegate('.common_skill_item', 'touchend');
            this.skillDom.find('#beauty_flower_add').unbind('touchend');
            this.presentDom.find('#beauty_present_hidebtn').unbind('touchend');
            this.presentDom.undelegate('.addtype', 'touchend');
            this.presentDom.find('.pop_btn_middle').unbind('touchend');
            this.mainDom.undelegate('#beauty_levelup_btn.pop_btn_small', 'touchend');
            this.skillDom.undelegate('#beauty_skill_reward.pop_btn_small', 'touchend');
            this.advanceDom.find('#beauty_advance_btn').unbind('touchend');
            this.chipDom.unbind('touchend');
            this.interactDom.find('#beauty_interact_hidebtn').unbind('touchend');
            this.advanceDom.find('#beauty_advance_hidebtn').unbind('touchend');
            this.mainDom.find('#beauty_convert_btn.pop_btn_small').unbind('touchend');
        },

        hide: function () {
            
            xGame.unlock();
            this.unbind();
            this.isshow = false; // 必须放在updateCd的前面
            xGame.updateCd('beautyAvoid', 0);
            xGame.updateCd('beautySkill', 0);
            xGame.updateCd('beautyInteractGreet', 0);
            xGame.updateCd('beautyLevelUp', 0);

            if (this.robChip) {
                this.robChipHide();
            }

            if (this.beautyScroller) {
                this.beautyScroller.destroy();
                this.beautyScroller = null;
            }

            // 互动奖励掉落计时器
            if (this.rewardTimer) {
                clearTimeout(this.rewardTimer);
                this.rewardTimer = null;
            }

            // 升级动画
            if (this.levelUpAnim) {
                this.levelUpAnim.destory();
                this.levelUpAnim = null;
            }

            // 升级动画计时器
            if (this.levelUpTimer) {
                clearTimeout(this.levelUpTimer);
                this.levelUpTimer = null;
            }

            // 美人私语计时器
            if (this.sayTimer) {
                clearTimeout(this.sayTimer);
                this.sayTimer = null;
            }

            // 升级刷新计时器
            if (this.levelUpRefreshTimer) {
                clearTimeout(this.levelUpRefreshTimer);
                this.levelUpRefreshTimer = null;
            }

            this.leftDom = null; // 界面的左侧部分
            this.mainDom = null; // 中间的碎片界面
            this.skillDom = null; // 界面右侧的技能部分
            this.interactDom = null; // 互动界面
            this.advanceDom = null; // 进阶界面
            this.listDom = null; // 美人头像列表
            this.activateNum = null; // 激活人数
            this.nowBeautyId = null; // 当前美人Id
            this.infoDom = null; // 玩法说明
            this.interactShow = null; // 是否显示互动界面
            this.advanceShow = null; // 是否显示进阶界面
            this.robChip = null; // 抢夺碎片界面
            this.greetTime = null; // 问候次数
            this.avoidWarCost = null; // 免战花费
            this.avoidWarTime = null; // 免战次数
            this.giveFlowerCost = null; // 送礼花费
            this.roseNum = null; // 拥有的玫瑰花数
            this.robChip = null; // 抢夺碎片界面
            this.robTime = null; // 抢夺次数
            this.robBuyTime = null; // 购买次数
            this.activateMaxNum = null; // 激活人数上限
            this.buyRoseCost = null; // 买花花费
            this.presentDom = null; // 赠礼界面
            this.beautyNameArr = null; // 美人名字
            this.presentInfo = null; // 赠礼界面信息
            this.diamondNum = null; // 拥有的宝石数量
            this.favourPoint = null; // 好感度
            this.interactHasData = null; // 互动界面是否有数据
            this.skillInfo = null; // 技能信息
            this.beautyLevel = null; // 美人等级
            this.clockDom = null; // 美人升级时钟
            this.skillRewardNum = null; // 特殊技能的奖励数量
            this.levelUping = null; // 是否正在升级中
            this.sayDom = null; // 美人私语
            this.avoiding = null; // 是否正在免战
            this.e.remove();
            this.e = null;

            if (beautypop) {
                beautypop = null;
            }
        }
    };
})();