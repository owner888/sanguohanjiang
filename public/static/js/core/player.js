/* player */
(function() {
    PlayerClass = function() {
        this.sh = null; //定时器
        this.e = null;
        this.lasttime = 0; //用于Phonegap后台恢复的时间戳
        this.info = {
            'uid': '',
            'nickname': '',
            'gender': 0,
            'level': 0,
            'exp': 0,
            'levelupexp': 0,
            'gold': null,
            'silver': 0,
            'act': 0,
            //军令
            'act_limit': 0,
            //军令上限
            'attack': 0,
            //是否开启蹴鞠大赛
            'actkemari': 0,
            'actkemari_type': 0,
            //是否开启我爱世界杯
            'worldcup': 0,
            'worldcup_type': 0,
            'defense': 0,
            'missionsite': 0,
            //当前位置 1地图势力列表 2城池列表 3对战武将列表
            'missionlevel': 0,
            //主公所在的势力地图ID
            'missionstage': 0,
            //主公所在的关卡地图ID
            'newpm': 0,
            'newmail': 0,
            'vip': 0,
            'exercise': 0,
            'sacrifice': 0,
            'blackmarket' : 0,
            'battlecd': 0,
            //推图CD
            'battlecdstatus': 0,
            //推图CD是否变红
            'soul': 0,
            'levy': null,
            'practice': null,
            'rank': 0,
            'vip_reward': null,
            'race': null,
            'newresult': 0,
            //通关奖励
            'daily_task': null,
            //日常任务
            'gift': null,
            //等级礼包
            'treasuremap': 0,
            //是否开启藏宝图
            'general_onsale': 0,
            //是否开启神将台
            'flop': 0,
            //是否开启四方来朝
            'dailysign': 0,
            //是否开启签到
            'openboss': 0,
            //BOSS是否开启
            'sg_ver': '',
            //圣诞签到
            'christmas': 0,
            //圣诞充值
            'christmaspay': 0,
            //圣诞神将
            'limitedshop': 0,
            //服务器维护信息
            'serverhold': '',
            //是否开启返利
            'rebate': 0,
            //是否开启月饼活动
            'mooncake': 0,
            //公测礼包兑换
            'exchange': 0,
            //是否在苹果审核期间
            'appreview': 0,
            //小于0代表没有国家，大于0代表国家id
            'country': 0,
            //是否显示国战图标
            'country_war': 0,
            //国战图标是否换为死战
            'country_lock': 0,
            //主城可收取矿
            'onmine': 0,
            //主城可收玉石作坊
            'onworkshop': 0,
            //玉石个数
            'jade': 0,
            //动态提醒id
            'trends_id': 0,
            //公告id
            'notice_id': 0,
            //点将台是否有新将领，气泡通知
            'ongeneral': 0,
            //全服福利
            'server_reward': 0,
            //是否是周六
            'is_saturday': 0,
            //国战开启时间
            'country_date': '',
            //国战敌方名称
            'enemy_name': '',
            //元旦领奖
            'newyeardailysign': 0,
            //天将神矿
            'jadegamble': 0,
            //春节返利
            'newyearpay' : 0,
            'new_role_reward': 0,
            //年夜饭
            'newyeardinner': 0,
            //是否提示年夜饭
            'dinner_get': 0,
            //是否有暴打名将
            'beatengod' : 0,
            //声望字段
            'reputation' : 0,
            //免费继承位置
            'free_inherit_site' : 0,
            //大熊返利
            'jump_rebate' : 0,
            //战神殿
            'god' : 0,
            //清明
            'chingming' : 0,
            //离开国家后7天内无法进入国家远征
            'leave_cd' : 0,
            //战神殿可报名
            'god_entry' : 0,
            'gvg' : 0,
            //91帐号绑定
            'bind91' : 0,
            'country_job': 0,
            'oncountrymine': 0,
            //是否绑定了微博
            'weibobind' : 0,
            //任务
            'mainquest': [],
            'edifice': [],
            //神树
            'sacredtree': 0,
            'anniversary': 0,
            //群雄逐鹿
            'compete': 0,
            //圣诞
            'christmasgift': 0,
            'ndgift': 0, //91用户迁移礼包是否领取
            //vip回馈
            'viprepay': 0,
            //春节商城
            'springshop': 0,
            'jump_rebate_type': 0,
            'chingming_type': 0,
            'christmasgift_type': 0,
            // 年兽
            'newyearboss': 0,
            'opennewyearboss': 0,
            'newyearpay_type': 0,
            'compete_type': 0,
            'springlottery': 0,
            'first_recharge': 0,//首充礼包
            'levelgift': 0,
            'newgift': 0,
            'levelgift_popo': 0,//是否显示提示图标
            'newgift_popo': 0,
            'activity_popo': 0,
            'newgift_starttime': 0, //用户注册的时间
            'newgift_msg': [],  // 新服礼包的推送消息
            'rush_popo': 0,
            'guest': 0, // 注册大礼包
            'god_reward': 0, //战神殿奖励预览
            'acttreasure': 0, // 踏青寻宝
            'acttreasure_popo': 0, // 寻宝感叹号
            'openbossreward':0, //世界boss感叹号
            //老兵召回
            'recall': 0,
            'war': 0, // 武斗会活动图标
            'war_entry': 0, // 武斗会报名状态
            'pass_general': 0,//过关斩将
            'actthirty': 0,
            'total_login': 0,
            'general_only': 0,
            'acttanabata': 0, //七夕
            'moon': 0,//中秋节
            'national_celebration':0,//国家庆典
            'login_reward': 0, // 登录返利
            'paychecks': 0, // 招募好友
            'national_day': 0,//领红包
            'halloween': 0,//万圣节
            'actenrichment': 0, //貔貅
            'sevenstar': 0,
            'christmasbuy' : 0,//圣诞团购
        };

        this.expBarLength = 119;
        this.worldareanisshow = false;
        this.binding91Show = false;
        this.missionThread;
        this.finishNum;
        this.onlyIndex;
        this.currentSite;

        /* 活动状态列表 */
        this.activityList = [];

        /* 是否已设置主线任务提醒 */
        this.mainquestNotice = false;
        this.newgiftNoticeSet = false;
    };

    PlayerClass.prototype = {

        init: function() {
        },

        initCd: function() {

            var dom_header = $('#header');

            //this.info.serverhold = '游戏正在维护，请稍后访问！<br/>预计开启时间：23:00';
            if (this.info.serverhold != '') {
                if (timer) {
                    timer.pause();
                }
                xGame.showWaitmsg(this.info.serverhold);
                return false;
            }

            xGame.checkMenuOpen();

            //战斗CD
            xGame.updateCd('battle', this.info.battlecd);

            if (this.info.battlecdstatus == 1) {
                $('#battle_timer').children('span').addClass('red');
            } else {
                $('#battle_timer').children('span').removeClass('red');
            }
            //侧边征收
            if (this.info.levy) {
                if (parseInt(this.info.levy.times) == 0) {
                    this.info.levy.cd = 0;
                }
                xGame.updateCd('levy', this.info.levy.cd);
            }

            //侧边训练
            this.showPracticeSider(this.info.practice.timers);

            if (parseInt(this.info.newpm) == 1) {
                /*不做任何操作，只初始化*/
                xGame.showpop('mail');
            }

            if (parseInt(this.info.newmail) > 0 || (parseInt(this.info.notice_id) > 0 && parseInt(this.info.notice_id) != parseInt(xGame.xsGet(xGame.server_prefix + 'notice_id')))) {
                $('#footer').find('.menu_mailbox').addClass('tada').unbind('touched').one('touchend',
                function() {
                    $(this).removeClass('tada');
                })
            } else {
                $('#footer').find('.menu_mailbox').removeClass('tada');
            }

            /* 排行信息 */
            if (typeof Platform != 'undefined' && Platform.id != 'h5') {
                if (this.info.rank > 0) {
                    var dom_arena = $('#sider_arena');
                    dom_arena.find('p').text(lang('rank', [this.info.rank]));
                    dom_arena.show();
                    dom_arena = null;
                } else {
                    $('#sider_arena').hide();
                }
            }
            
            /* 主线任务 */
            //if (typeof Platform != 'undefined' && Platform.id != 'h5') {
                if (this.info.mainquest.length > 0) {
                    this.buildMissionList();
                    $('#mission_wrap').show();
                    this.missionAutoMove();
                } else {
                    $('#mission_wrap').hide();
                    if (this.missionThread != null) {
                        clearInterval(this.missionThread);
                        this.missionThread = null;
                    }
                }
            //}
            
            /* 该领取通关奖励 */
            //this.info.newresult = 1;
            if (this.info.newresult > 0) {
                if ($('#passreward_box_wrap').length == 0 && xGame.nowWorld == 'city') {
                    setTimeout(function() {
                        var isAlldone = xGame.xsGet(player.info.uid + 'shilialldone');
                        if(isAlldone == 'solo') {
                            miscpop.passShiliReward(player.info.newresult, true);
                        } else {
                            miscpop.passShiliReward(player.info.newresult);
                        }
                    },
                    1000);
                }
            }

            /* 日常任务 */
            var dailytask_wrap = $('#dailytask_wrap');
            switch (parseInt(this.info.daily_task.status)) {
            case 0:
                dailytask_wrap.hide();
                break;
            case 1:
                dailytask_wrap.unbind('touchend').bind('touchend',
                function() {
                    var activitymenu = $('#header').find('.header_activity_menu');
                    var menucontrol = $('#header').find('#header_activitymenu_control');
                    if (activitymenu && menucontrol) {
                        menucontrol.removeClass('open');
                        xGame.doAnimate(activitymenu, 'fadeOutTR', '0.35s',
                        function() {
                            activitymenu.hide();
                        });
                    }
                    
                    xGame.showpop('dailytask');
                });
                dailytask_wrap.find('p').html(lang('task_left') + '(' + this.info.daily_task.number + ')');
                dailytask_wrap.show();
                break;
            case 2:
                dailytask_wrap.unbind('touchend').bind('touchend',
                function() {
                    xGame.showpop('dailytask');
                });
                var dailytask_wrap_p = dailytask_wrap.find('p');
                dailytask_wrap_p.html(this.info.daily_task.name + '<em>' + this.info.daily_task.plan + '</em>/' + this.info.daily_task.value + '');
                if (this.info.daily_task.plan >= this.info.daily_task.value) {
                    //任务已完成，可以领取奖励
                    dailytask_wrap_p.css({
                        'color': '#72FF3F'
                    });
                }
                dailytask_wrap.show();
                if (this.info.daily_task.name.length + 4 > 10) {
                    dailytask_wrap_p.addClass('sp');
                } else {
                    dailytask_wrap_p.removeClass('sp');
                }
                dailytask_wrap_p = null;
                break;
            }
            dailytask_wrap = null;

            /* 侧边栏等级奖励 */
            //this.showLevelReward(this.info.gift);

            /*（首充改版） 只有当等于1时才可以领取，才显示那个div*/
            if (this.info.first_recharge == 1 || this.info.first_recharge == 2 || this.info.first_recharge == 3) {
                dom_header.find('#header_firstpay_btn_wrap').show();
                if((typeof firstpaygiftpop == 'undefined' || firstpaygiftpop.e == null) && player.info.first_recharge == 2) {
                    //自动弹出
                    if(xGame.nowWorld == '' || xGame.nowWorld == 'login' || xGame.nowWorld == 'city') {
                        xGame.showpop('firstpaygift');
                    }
                }
            } else {
                dom_header.find('#header_firstpay_btn_wrap').hide();
            }

            if (this.info.levelgift == 1) {
                dom_header.find('#header_levelgift_btn_wrap').show();
                if (this.info.levelgift_popo == 1) {
                    dom_header.find('#header_levelgift_btn_wrap').children('.popo').show();
                }
            }

            if (this.info.newgift == 1) {
                dom_header.find('#header_newgift_btn_wrap').show();
                if (this.info.newgift_popo == 1) {
                    dom_header.find('#header_newgift_btn_wrap').children('.popo').show();
                }
            }

            if (this.info.activity_popo == 1 || this.info.openbossreward== 1) {
                dom_header.find('#header_activity_btn_wrap').children('.popo').show();
            }

            if (this.info.guest == 1) {
                dom_header.find('#header_regreward_btn_wrap').show();
            }
            /*
             * 活动按钮开始
             */
            var newActivityList = [];
            /* 判断是否拿3天的新手礼包 */
            if (this.info.new_role_reward == 1) {
                newActivityList.push('newbiegift');
            }
            /* 判断是否显示竞速 */
            if (this.info.race == 1) {
                newActivityList.push('rush');
                
                if (this.info.rush_popo == 1) {
                    $('#rush_btn').children('.popo').show();
                }
            }
            /* 判断是否显示整军演武 */
            if (this.info.exercise == 1) {
                newActivityList.push('exercise');
            }
            /* 判断是否显示祭祀 */
            if (this.info.sacrifice == 1) {
                newActivityList.push('sacrifice');
            }
            /* 判断是否显示神将台 */
            if (this.info.general_onsale == 1) {
                newActivityList.push('godwill');
            }
            /* 判断是否显示四方来朝 */
            if (this.info.flop == 1) {
                newActivityList.push('worship');
            }
            /* 判断是否开启月饼 */
            if (this.info.mooncake == 1) {
                newActivityList.push('mooncake');
            }
            /* 判断是否显示签到 */
            if (this.info.dailysign == 1) {
                newActivityList.push('dailysign');
            }
            /* 判断是否显示返利 */
            if (this.info.rebate == 1) {
                newActivityList.push('rebate');
            }
            /* 判断是否显示圣诞返利 */
            if (this.info.christmaspay == 1) {
                newActivityList.push('christmaspay');
            }
            /* 判断是否显示圣诞签到 */
            if (this.info.christmas == 1) {
                newActivityList.push('christmassign');
            }

            /* 判断是否显示黑市 */
            if(this.info.blackmarket == 1) {
                newActivityList.push('blackmarket');
            }

            /* 判断是否显示元旦领奖 */
            if (this.info.newyeardailysign == 1) {
                newActivityList.push('ganen');
            }
            /* 判断是否显示神矿 */
            if (this.info.jadegamble == 1) {
                newActivityList.push('godmine');
            }

            /* 判断是否显示春节返利 */
            if (this.info.newyearpay == 1) {
                newActivityList.push('newyearpay');
            }

            if (this.info.newyeardinner == 1) {
                newActivityList.push('newyeardinner');
            }

            /* 开启年夜饭提示 */
            if (this.info.dinner_get == 1) {
                miscpop.openDinnerConfirm();
            }

            if (this.info.beatengod == 1) {
                newActivityList.push('beatengod');
            }

            if (this.info.chingming == 1) {
                newActivityList.push('qingming');
            }

            if (this.info.jump_rebate == 1) {
                newActivityList.push('bear');
            }

            if (this.info.gvg == 1) {
                newActivityList.push('gvg');
            }

            if (this.info.limitedshop == 1) {
                newActivityList.push('limitedgod');
            }
            
            if (this.info.sacredtree == 1) {
                newActivityList.push('sacredtree');
            }
            
            if (this.info.anniversary == 1) {
                newActivityList.push('anniversary');
            }
	    
             if (this.info.recall == 1) {
                newActivityList.push('recall');
            }

            if (this.info.compete == 1) {
                newActivityList.push('zhulu');
            }

            /*vip回馈*/
            if (this.info.viprepay == 1) {
                newActivityList.push('viprepay');
            }
            
            /*春节商城*/
            if (this.info.springshop == 1) {
                newActivityList.push('springshop');
            }
            
            /*圣诞礼包*/
            if (this.info.christmasgift == 1) {
                newActivityList.push('christmasgift');
            }

            // 年兽
            if (this.info.newyearboss == 1) {
                newActivityList.push('nian');
            }

            if (this.info.springlottery == 1) {
                newActivityList.push('newyearlottery');                
            }

            //战神殿奖励预览
            if (this.info.god_reward == 1) {
                newActivityList.push('championsreward');                
            }
            //过关斩将
            if (this.info.pass_general == 1) {
                newActivityList.push('breakthrough');                
            }

            if (this.info.acttreasure == 1) {
                newActivityList.push('acttreasure');
            }

            // 30天内调用推送消息方法
            var isIn30days = false;
            if (Date.parse(new Date()) <= parseInt(this.info.newgift_starttime) * 1000 + 30 * 24 * 60 * 60 * 1000) {
                isIn30days = true;
            }
            this.setNewgiftNotice(isIn30days);
	    
            //god_entry= 1可报名  god_entry=2 开战 god_entry=3 已报名
            if (this.info.god_entry == 1 || this.info.god_entry == 2 || this.info.god_entry == 3) {
                newActivityList.push('champions');
            }

            // 武斗会活动图标
            if (this.info.war == 1) {
                newActivityList.push('war');
            }
            
            /*判斷是否显示蹴鞠大赛*/
            if (this.info.actkemari == 1) {
                newActivityList.push('kemari');
            }

            /*判斷是否显示我爱世界杯*/
            if (this.info.worldcup == 1) {
                newActivityList.push('worldcup');
            }
	        /*充值赠礼*/
            if (this.info.actthirty == 1) {
                newActivityList.push('chargegift');
            }
            /*连续签到*/
            if (this.info.total_login == 1) {
                newActivityList.push('signin');
            }
            /*绝版神将*/
            if (this.info.general_only == 1) {
                newActivityList.push('outprintgod');
            }
            /*灵雀赠礼*/
             if (this.info.acttanabata == 1) {
                newActivityList.push('valentine');
            }
            /*中秋送月饼*/
            if (this.info.moon == 1) {
                newActivityList.push('midautumn');
            }
            /*国家庆典*/
            if (this.info.national_celebration == 1 ) {
                newActivityList.push('nationcel');
            }

            /*貔貅*/
            if (this.info.actenrichment == 1){
                newActivityList.push('enrichment');
            }

            /*七星灯*/
            if (this.info.sevenstar == 1){
                newActivityList.push('sevenstar');
            }

            // 登录返礼
            if (this.info.login_reward == 1) {
                newActivityList.push('loginreward');
            }
	    
            if (this.info.paychecks == 1) {
                newActivityList.push('paychecks');
            }

            //领红包
            if (this.info.national_day == 1) {
                newActivityList.push('nationalday');
            }

            //万圣节
            if (this.info.halloween == 1) {
                newActivityList.push('halloween');
            }
            
            //圣诞团购
            if(this.info.christmasbuy == 1) {
                newActivityList.push('christmasbuy');
            }
            //91助手-有奖更新(仅用于悍将简体)
            //重要：3.2版本以后不再更新
            /*
            if (this.info.ndgift == 1) {
                newActivityList.push('ndgift');

                setTimeout(function() {
                    if(xGame.nowWorld == 'city') {
                        var nowdate = new Date();
                        var date_str = nowdate.getFullYear() + '' + (nowdate.getMonth() + 1) + '' + nowdate.getDate();
                        var last_ndgift_date = xGame.xsGet(xGame.server_prefix + 'last_ndgift');
                        
                        //弹窗条件：时间对不上
                        if(date_str != last_ndgift_date) {
                            if(typeof ndgiftpop == 'undefined' || ndgiftpop.isshow == false) {
                                xGame.showpop('ndgift');
                                xGame.xsSet(xGame.server_prefix + 'last_ndgift', date_str);
                            }
                       }
                    }
                }, 3000);
            }*/

            /* 判断是否显示国战: 国战一定要写在最后 */
            if (this.info.country_war > 0 || xGame.nowWorld == 'country') {//1为报名，2为马上开始了,3为国家管理层可以申请占领了,4可以直接战斗
                if (this.info.country_war != 3) {
                    newActivityList.push('cbattle');

                    if (parseInt(player.info.is_saturday) == 1) {
                        miscpop.openCbattleConfirm(this.info.country_war);
                    }
                }
            }

            //判断有改变，才去处理（直接清理掉所有的活动，重新渲染）
            if(JSON.stringify(newActivityList) != JSON.stringify(this.activityList)) {
                //保存当前活动列表
                this.activityList = newActivityList;
                //渲染活动列表
                this.renderActivity();
                
            } else {
                //console.log('activity is not modified');
            }

            /* 每日提醒(只在主城和地图里弹) */
            if (xGame.guide == false && this.info.trends_id > 0 && parseInt(this.info.level) >= 20) {
                setTimeout(function() {
                    if(xGame.nowWorld == 'city' || xGame.nowWorld == 'map') {
                        var nowdate = new Date();
                        var date_str = nowdate.getFullYear() + '' + (nowdate.getMonth() + 1) + '' + nowdate.getDate();

                        var last_trends_id = xGame.xsGet(xGame.server_prefix + 'last_trends_id');
                        var last_trends_date = xGame.xsGet(xGame.server_prefix + 'last_trends_date');

                        /*
                         * 弹窗条件：
                         *   1.本地没有存数据
                         *   2.或本地ID与服务器ID对不上
                         *   3.或时间对不上
                         */
                        if(!last_trends_id || !last_trends_date
                            || last_trends_id != player.info.trends_id
                            || last_trends_date != date_str) {
                            //弹窗
                            if(typeof trendspop == 'undefined' || trendspop.isshow == false) {
                                xGame.showpop('trends', {id: player.info.trends_id, date: date_str});
                            }
                        }
                    }
                }, 2500);
            }

            /* 开启BOSS */
            if (this.info.openboss == 1) {
                miscpop.openBossConfirm();
            }
            
            /* 开启年兽 */
            if (this.info.opennewyearboss == 1) {
                miscpop.openNianConfirm();
            }
            
            /* 推送更新 */
            if (this.info.sg_ver != '' && this.info.sg_ver != localStorage.getItem('sg_ver')) {
                timer.pause();
                initmsg(lang('update_msg'));
            }

            /* 弹出跨服奖励领取窗口 */
            if(this.worldareanisshow == false && this.info.server_reward != 0) {
                this.serverReward(this.info.server_reward);
            }
            /* 91弹出帐号绑定界面 */
            /*
            if(xGame.nowWorld != 'login' && xGame.nowWorld != 'role' && xGame.guide == false && this.binding91Show == false && parseInt(this.info.bind91) == 1) {
                var that = this;
                xGame.confirm.show(lang('binding_user_bind'), lang('binding_user_notice'), 1, function() {
                    that.binding91Show = true;
                    xGame.showpop('binding91');
                    xGame.confirm.hide();
                });
            }*/
            
        },

        /*全服奖励*/
        serverReward: function(data) {
            var that = this;

            if(that.worldareanisshow == false) {//新建弹窗
                var tmpl = xGame.loadPopTmpl('welfare');
                that.worldareanisshow = true;
            } else {//弹窗已存在，直接取DOM
                var tmpl = $('#welfarepop');
                tmpl.show();
            }

            tmpl.find('#welfare_name').html(data.nickname);
            var img = 'pop/worshippop_type2.png';
            if(data.type == 2) {
                img = 'pop/pop_gem1.png';
            } else if(data.type == 3) {
                img = 'pop/pop_gem2.png';
            } else if(data.type == 4) {
                img = 'pop/pop_gem3.png';
            } else if(data.type == 5) {
                img = 'pop/worshippop_type3.png';
            } else if(data.type == 6) {
                img = 'pop/worshippop_type4.png';
            } else if(data.type == 7) {
                img = 'pop/worshippop_type5.png';
            } else if(data.type == 8) {
                img = 'pop/worshippop_type6.png';
            }
            tmpl.find('#welfare_reward').html(data.num).css({'background-image':'url(' + staticUrl + 'img/'+img+')'});
            tmpl.find('.pop_btn_middle').hide();
            tmpl.find('#welfare_btn').show();
            tmpl.find('#welfare_btn').unbind('touchend').bind('touchend', function() {
                var successFunc = function(res) {
                    switch(parseInt(res.status)){
                        case 1:
                            tmpl.hide();
                            //更新银币军令
                            player.updateinfo({'silver':parseInt(res.silver), 'act':parseInt(res.act)});
                            xGame.toast.show(lang('get_reward_success'));
                            if(res.server_reward != '') {
                                that.serverReward(res.server_reward);
                            } else {
                                tmpl.remove();
                                tmpl = null;
                            }
                            break;
                        default:
                            xGame.toast.show(lang('no_reward_try_again'));
                            tmpl.hide();
                            tmpl.remove();
                            tmpl = null;
                            break;
                    }
                };
                loadContents({
                    'c': 'worldarena',
                    'm': 'get_server_reward'
                },successFunc, true);
            });
        },

        initInfoAndEnter: function() { //初始化用户信息并进入游戏主界面
            var that = this;

            $('#worldloading').show();

            var successFunc = function(res) {

                $('#worldloading').hide();

                /* 更新玩家信息 */
                that.updateinfo(res);

                if (that.info.gender == 0) { //没有选择角色
                    roleWorld = roleWorld || (new roleWorldClass);
                    roleWorld.init(true); //true这个参数是让选角色界面隐藏back按钮（否则会出错）
                    xGame.nowWorld = 'role';
                } else {
                    /* CD开始运转 */
                    that.initCd();

                    /* 进入游戏主城 */
                    if (player.info.level && player.info.level >= 20) {
                        xGame.loadWorld('city');
                    } else {
                        guide.loadGuide();
                    }
                    //这个函数只在刷新的时候会被调用
                    if(typeof Platform != 'undefined' && Platform.id == 'h5') {
                        //不在引导中，每次刷新都显示一次
                        if (xGame.guide == false) {
                            miscpop.showAddToScreen();
                        }
                        //提示去app store
                        miscpop.showStandaloneTips();
                    }

                    xGame.updateCd('updatePlayer', null);
                    xGame.updateCd('chat', 0);
                    if (gAudio) {
                        gAudio.playbgm('city_bgm');
                    }

                    //不在新手引导时，弹活动
                    /*
                    if (that.info.activities == 1 && (xGame.guide == false || (xGame.guide == true && guide.now >= 138))) {
                        //setTimeout(function() {
                        xGame.showpop('activities');
                        //}, 1000);
                    }*/

                    //简单检查一下支付
                    if (xGame.xsGet('pay_receipt')) { //有未完成的账单
                        console.log('启动时检查支付');
                        iospayCheck();
                    }

                    xGame.initLocalNotication();
                    //2013-8-22 关卡bug修复
                    if (xGame.guide == false) {
                        xGame.xsRemove(player.info.uid + 'guide_hide_page', true);
                    }

                }

                successFunc = null;
                that = null;
            };
            loadContents({
                'c': 'member',
                'm': 'index'
            },
            successFunc, true, function() {
                initmsg(lang('no_internet_msg'));
            });
        },

        /* 重新获取用户数据 */
        reloadInfo: function(callback, hide) {
            var that = this;
            var successFunc = function(res) {
                that.updateinfo(res);
                that.initCd();
                that.toggleActivityPopo();
                callback && callback();
                successFunc = null;
                that = null;
            };

            loadContents({
                'c': 'member',
                'm': 'index'
            },
            successFunc, hide != undefined ? hide : true, function() {
                initmsg(lang('no_internet_msg'));
            });

            successFunc = null;

        },

        _show: function() {
            var that = this;

            var dom_header = $('#header');
            var old;

            old = dom_header.find('.avatar');
            if (old.css('background-image') == 'none' && that.info.gender > 0) {
                old.addClass('avatar' + that.info.gender);
            }
            old = dom_header.find('.name');
            if (old.text() != that.info.nickname) {
                old.html('<em' + (that.info.nickname.length > 5 ? ' style="font-size:18px;"': '') + '>' + that.info.nickname + '</em>');
            }
            old = dom_header.find('.level');
            if (old.text() != that.info.level) {
                old.html('' + that.info.level);
            }
            old = $('#vip_bj');
            if (old.text() != that.info.vip) {
                old.html('VIP.' + that.info.vip);
            }
            if (that.info.vip >= 1) {
                $('#vip_bj').show();
            } else {
                $('#vip_bj').hide();
            }
            var act_old = $('#act').find('p');
            if (act_old.text() != that.info.act + '/' + that.info.act_limit) {
                xGame.doAnimate2(act_old, 'changcolor', 100, 7,
                function() {
                    act_old.html('' + that.info.act + '/' + that.info.act_limit);
                    act_old = null;
                });
            }

            var silverword = parseInt(that.info.silver) > 999999 ? that.getWan(that.info.silver) : that.info.silver + '';
            var coin_old = $('#coin').find('p');
            if (coin_old.text() != silverword) {
                xGame.doAnimate2(coin_old, 'changcolor', 100, 7,
                function() {
                    coin_old.html(silverword);
                    coin_old = null;
                });
            }
            var goldword = parseInt(that.info.gold) > 999999 ? that.getWan(that.info.gold) : that.info.gold + '';
            var gold_old = $('#gold').find('p');
            if (gold_old.text() != goldword) {
                xGame.doAnimate2(gold_old, 'changcolor', 100, 7,
                function() {
                    gold_old.text(goldword);
                });
            }

            old = null;
            dom_header = null;
            that._expBar();

        },

        getWan: function(num) {
            return Math.floor(parseInt(num) / 10000) + lang('10k');
        },

        _expBar: function() {
            var that = this;
            /*作为判断依据必须保留，所以这里用class的方式选择显示百分比还是数字*/
            var avatar_wrap = $('#header').find('.avatar_wrap');
            var old = avatar_wrap.find('.exp').text();
            if (old != that.info.exp + '/' + that.info.levelupexp) {
                var expLength = parseInt(that.info.exp) / parseInt(that.info.levelupexp) * that.expBarLength;
                expLength = parseInt(expLength);
                avatar_wrap.find('.expbar').width(expLength);
                avatar_wrap.find('.exp').text('' + that.info.exp + '/' + that.info.levelupexp);
                avatar_wrap.find('.explong').text(Math.floor(that.info.exp / that.info.levelupexp * 100) + '%');

                if (that.info.levelupexp > 10000) {
                    avatar_wrap.addClass('toolong');
                } else {
                    avatar_wrap.removeClass('toolong');
                }
            }
            avatar_wrap = null;
        },

        updateinfo: function(msg) {
            var that = this;
            //save data
            for (var key in msg) {
                if (key == 'gold' && that.info[key] != null && parseInt(msg[key]) < parseInt(that.info[key])) {
                    that.taskaction(5);
                } else if(key == 'vip' && parseInt(that.info[key]) < 3 && parseInt(msg[key]) >= 3) {//升到VIP3，清掉所有强化CD、征收CD、征战CD
                    //console.log('VIP3达到，清理CD');
                    //征战CD
                    msg.attackcd = {status: 0, cd: 0};
                    //征收CD
                    msg.levy.cd = 0;
                    msg.levy.status = 0;
                    if(typeof levypop != 'undefined' && levypop.e != null) {
                        xGame.updateCd('levy', 0);
                    }
                    //强化CD
                    if(typeof strongpop != 'undefined' && strongpop.e != null) {
                        xGame.updateCd('strong', 0);
                    }
                }
                if (typeof that.info[key] != 'undefined' && msg[key] != null) {
                    that.info[key] = msg[key];
                }
            }

            //更新战斗CD
            if (msg.attackcd) {
                that.info.battlecd = msg.attackcd.cd;
                that.info.battlecdstatus = msg.attackcd.status;
            }

            that._show();
        },

        showPracticeSider: function(time) {

            if (this.info.practice) {
                var dom_practice = $('#sider_practice');
                if (time > 0) {
                    xGame.updateCd('siderPractice', time);
                    dom_practice.find('p').attr({
                        'class': 'cd'
                    });
                } else {
                    xGame.updateCd('siderPractice', 0);
                    dom_practice.find('p').attr({
                        'class': 'nocd'
                    }).text(lang('can_practice_hero'));
                }
                dom_practice = null;
            }

        },

        taskaction: function(type, batch) {//为批量任务添加了参数batch
            /*完成任务的方法，供其他地方调用*/
            if (parseInt(type) == parseInt(player.info.daily_task.type)) {

                var dailytask_wrap_p = $('#dailytask_wrap').find('p');

                player.info.daily_task.plan += 1;
                var number = parseInt(player.info.daily_task.plan);
                if (number >= parseInt(player.info.daily_task.value) || batch == true) { 
                    number = parseInt(player.info.daily_task.value);
                    dailytask_wrap_p.css({
                        'color': '#72E957'
                    });
                }
                dailytask_wrap_p.find('em').html(number);
                dailytask_wrap_p = null;
            }
        },

        /* 2013-7-19日注释，新的主线任务，这个方法废弃
        showLevelReward: function(gift) {
            var levelreward_wrap = $('#levelreward_wrap');
            if (gift) {
                if (gift.open == 1) {
                    levelreward_wrap.addClass('open');
                } else {
                    levelreward_wrap.removeClass('open');
                }
                levelreward_wrap.find('p').html(gift.title);
                levelreward_wrap.show();
                if (gift.title.length > 7) {
                    levelreward_wrap.find('p').addClass('sp');
                } else {
                    levelreward_wrap.find('p').removeClass('sp');
                }
            } else {
                levelreward_wrap.hide();
            }
            levelreward_wrap = null;
        },
        */

        /* 手动关闭某个活动图标 */
        hideActivity: function(activityID) {
            if(!miscpop.in_array(activityID, this.activityList)) {
                return false;
            }
            this.activityList.splice(this.activityList.indexOf(activityID), 1);
            this.renderActivity();
        },

        /* 手动开启某个活动图标 */
        showActivity: function(activityID) {
            if(miscpop.in_array(activityID, this.activityList)) {
                return false;
            }
            this.activityList.push(activityID);
            this.renderActivity();
        },
        
        renderActivity: function() {
            //this.activityList;

            var html = '';
            var itemnum = 0;
            var itemLimit = Platform.id == 'h5' ? 2: 3;//单列的活动最大数[h5版本只放2个]
            var linenum = 1;//列数
            var popoDom = $('#header_activitymenu_control').find('.popo');

            // 支付类活动未迁移（服务端无接口），不进精彩活动菜单，避免点击报错/转圈
            var HJ_EXCLUDE_POP = ['rebate', 'newyearpay', 'paychecks', 'christmaspay', 'firstpay', 'first_recharge', 'uc_paymatch', 'viprepay'];

            for(var i = 0; i < this.activityList.length; i++) {
                var activityID0 = this.activityList[i];
                if (HJ_EXCLUDE_POP.indexOf(activityID0) >= 0 || activityID0.indexOf('pay') === 0) {
                    continue;
                }
                if(itemnum == 0) {//第一个活动
                    html += '<li class="li'+linenum+'" id="li'+linenum+'">';
                } else if(itemnum == itemLimit) {//超过单列最大限制个，需要单起一列了
                    itemnum = 0;
                    linenum++;
                    html += '</li><li class="li'+linenum+'" id="li'+linenum+'">';
                }

                //活动
                var activityID = this.activityList[i];
                if ((this.info[activityID] == 1 &&  this.info[activityID + '_type'] == 1) || (activityID == 'qingming' && this.info['chingming_type'] == 1) || (activityID == 'bear' && this.info['jump_rebate_type'] == 1) || (activityID == 'zhulu' && this.info['compete_type'] == 1)) {
                    html += '<div pop="' + activityID + '" class="rightsidebtn btn new" id="' + activityID + '_btn">';
                } else {
                    html += '<div pop="' + activityID + '" class="rightsidebtn btn" id="' + activityID + '_btn">';
                    if (this.info[activityID + '_popo'] == 0) { // 提示图标
                        html += '<span class="popo"></span>';
                    } else if (this.info[activityID + '_popo'] == 1) {
                        html += '<span class="popo" style="display:block;"></span>';
                        popoDom.show();
                    }
                }
                var class_suf_arr = [];//额外样式

                //国战图标有特殊处理
                if(activityID == 'cbattle') {
                    //如果是国战，并且在周六，大于6点，让这个图标闪动
                    if((this.info.is_saturday == 1 && this.info.country_war == 1) || this.info.country_war == 2 || this.info.country_war == 4) {
                        class_suf_arr.push('tada');
                    }
                    //死战
                    if(this.info.country_lock == 1) {
                        class_suf_arr.push('force');
                    }
                }

                html += '<div class="main '+class_suf_arr.join(' ')+'"></div>';
                html += '</div>';

                itemnum++;
            }
            html += '</li>';
            
            var dom_header = $('#header');
            var dom_header_menu = dom_header.find('.header_activity_menu');
            dom_header.find('.rightside2_wrap').html(html);
            dom_header_menu.hide();
        },

        buildMissionList: function() {
            var html = '';
            var temp = '';
            this.finishNum = 0;
            this.onlyIndex = 0;
            var mainquestHaveNotice = false;

            if (this.info.mainquest.length > 1) {
                if (this.info.mainquest[0].status == 1) {
                    temp = '<li class="g">' + this.info.mainquest[0].name + '</li>';
                } else {
                    temp = '<li>' + this.info.mainquest[0].name + '</li>';
                }
            }
            for (var i = 0;i < this.info.mainquest.length;i ++) {
                if (this.info.mainquest[i].status == 1) {
                    html += '<li class="g">' + this.info.mainquest[i].name + '</li>';
                    this.finishNum += 1;
                    this.onlyIndex = i + 1;
                } else  {
                    html += '<li>' + this.info.mainquest[i].name + '</li>';
                }
                if(this.info.mainquest[i].task_type == 20) {//主线任务每日登录的通知处理
                    mainquestHaveNotice = true;//有主线任务的通知
                    this.setMainquestNotice();
                }
            }
            if(mainquestHaveNotice == false) {//一个主线任务的通知都没有，强制取消通知
                this.cancelMainquestNotice();
            }

            var mission_wrap = $('#mission_wrap');
            mission_wrap.find('ul').html(html + temp);
            //显示小叹号
            if (this.finishNum > 0) {
                if (mission_wrap.find('.popo').length == 0) {
                    mission_wrap.append('<span class="popo"></span>');
                }
            } else {
                mission_wrap.find('.popo').remove();
            }

            html = null;
            temp = null;
            mission_wrap = null;
        },
        
        /*修改为无缝对接了*/
        missionAutoMove: function() {
            if (this.missionThread != null) {
                clearInterval(this.missionThread);
                this.missionThread = null;
            }
            //更新玩家数据的时候会有bug，这里处理下
            if (!miscpop.in_array(xGame.nowWorld, ['city', 'map', 'treasure', 'countrymap']) || xGame.menustatus == false) {
                return false;
            }
            var mission_wrap = $('#mission_wrap');
            if (this.info.mainquest.length > 0 && this.info.mainquest.length != 1 && this.finishNum != 1) {
                if (this.currentSite == null) {
                    this.currentSite = 1;
                }
                this.missionThread = setInterval(function() {
                    player.currentSite += 1;
                    if (player.currentSite == player.info.mainquest.length + 1) {
                        mission_wrap.find('ul').css('-webkit-transform', 'translateY(' + (-30 * (player.currentSite - 1)) + 'px)').one('webkitTransitionEnd', function() {
                            $(this).addClass('no_anim').css('-webkit-transform', 'translateY(0px)');
                            setTimeout(function() {
                                mission_wrap.find('ul').removeClass('no_anim');
                            }, 100);
                            player.currentSite = 1;
                        });
                    } else {
                        mission_wrap.find('ul').css('-webkit-transform', 'translateY(' + (-30 * (player.currentSite - 1)) + 'px)');
                    }
                }, 3000);
            } else {
                if (this.onlyIndex > 0) {//说明有已经完成的，到这个唯一完成的那里去
                    mission_wrap.find('ul').css('-webkit-transform', 'translateY(' + (-30 * (this.onlyIndex - 1)) + 'px)');
                } else {//说明没有完成的，到第一个去
                    mission_wrap.find('ul').css('-webkit-transform', 'translateY(0px)');
                }
            }
        },

        /* 设置主线任务提醒 */
        setMainquestNotice: function() {
            if(this.mainquestNotice == true) {
                //console.log('主线任务：每日登录推送已设置');
                return;
            }

            //console.log('主线任务：每日登录需要推送');
            this.mainquestNotice = true;
            if(typeof cordova != 'undefined' && typeof window.plugins.localNotification != 'undefined') {
                window.plugins.localNotification.cancel('mainquest');

                //获取明天0点
                var now = new Date();
                //12:00 BOSS站
                var firetime = now.getFullYear() + '-' + (now.getMonth() + 1) + '-' + now.getDate();
                var nextdayTimestamp = miscpop.getTimestamp(firetime + ' 00:00:00') / 1000 + 60 * 60 * 24;
                //console.log('主线任务：每日登录推送时间戳：' + nextdayTimestamp);
                window.plugins.localNotification.add({
                    date: nextdayTimestamp, // your set date object
                    message: lang('mainquest_notice'),
                    badge: 1,
                    repeat: 'daily',//每天重复
                    id: 'mainquest',
                    sound:'sub.caf'
                });

            }
        },

        /* 取消主线任务提醒 */
        cancelMainquestNotice: function() {
            //console.log('主线任务：每日登录的推送被取消');
            this.mainquestNotice = false;
            if(typeof cordova != 'undefined' && typeof window.plugins.localNotification != 'undefined') {
                window.plugins.localNotification.cancel('mainquest');
            }
        },

        setNewgiftNotice: function(isIn30days) {

            var that = this;

            if(typeof cordova != 'undefined' && typeof window.plugins.localNotification != 'undefined') {
                
                if(that.newgiftNoticeSet == true) {
                    return;
                }

                that.newgiftNoticeSet = true;

                for (var i = 0; i < 4; i++) {
                    window.plugins.localNotification.cancel('newgift' + i);
                }
                
                if(isIn30days) {
                    var nowTime = Math.floor(Date.parse(new Date()) / 1000);
                    var startTime = parseInt(that.info.newgift_starttime);
                    var two = startTime + 2 * 86400;
                    var seven = startTime + 7 * 86400;
                    var fifteen = startTime + 15 * 86400;
                    var thirty = startTime + 30 * 86400;
                    var timeList = [two, seven, fifteen, thirty];

                    for (var i = 0, len = that.info.newgift_msg.length; i < len; i++) {
                        
                        if (nowTime <= timeList[i]) {
                            window.plugins.localNotification.add({
                                date: timeList[i],
                                message: that.info.newgift_msg[i],
                                badge: 1,
                                id: 'newgift' + i,
                                sound: 'sub.caf'
                            });
                        }
                    }
                }
                
            }
        },

        // 切换精彩活动的感叹号显示状态
        toggleActivityPopo: function () {

            var popo = $('#header_activitymenu_control').find('.popo');
            popo.hide();
            var activity = null;

            for(var i = 0; i < this.activityList.length; i++) {
                activity = this.activityList[i];

                if (this.info[activity + '_popo'] == 1) {
                    popo.show();
                    break;
                }
            }

            popo = null;
        },

        // 苹果审核期间屏蔽部分功能
        hidePopInAppreview: function () {
            
            if (this.info.appreview == 0) {
                return;
            }

            if (cityWorld && cityWorld.e != null) {
                cityWorld.e.find('#war_building').hide();
                cityWorld.e.find('#worldarena_building').hide();
                cityWorld.e.find('#arena_building').addClass('show').show();
            }

            if (activitypop && activitypop.e != null) {
                activitypop.e.find('#activity_invitation').hide();
            }

            if (systempop && systempop.e != null) {
                systempop.e.find('.godforge').hide();
            }
        },
        
    };

})();