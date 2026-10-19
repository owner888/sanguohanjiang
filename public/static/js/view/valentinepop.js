/* 灵雀赠礼 */
(function() {
    valentinePopClass = function() {
        this.e;
        this.isshow = false;
        this.anim = null;
        this.leftDom;
        this.rightDom;
        this.rewardScroller = null;
        this.rewardScroller2 = null;
        this.rewardDom = null;
        this.giftData = {}; //奖励数据，以id为键
        this.resData; //返回数据
        this.timer = null;
        this.isTimeout = [];
    };
    valentinePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.fillData(res);
                        break;
                    default:
                        $('#worldloading').hide();
                        xGame.toast.show(res.msg);
                        break;
                }
            };
            loadContents({
                'c': 'act_tanabata',
                'm': 'index'
            },
            Func); //加载数据
            Func = null;
        },
        updatepage: function() {
            var that = this;

            var Func = function(res) {
                /* 填充元宝总数 */
                that.e.find('#valentine_gold_num').html(that.getWan(res.all_gold));
                /* 填充左侧数据 */
                that.playerTopList(res.rank_list);
                that.leftReward(res.my_rank);
                /*填充右侧奖品以及数据*/
                that.fillRightData(res.list);

                if (that.timer != null) {
                    clearTimeout(that.timer);
                    that.timer = setTimeout(function () {
                        that.updatepage();
                    }, 900000);
                }
            };
            loadContents({
                'c': 'act_tanabata',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;
        },
        fillData: function(res) {
            var that = this;

            that.resData = res;
            this.isEnd = res.end; //判断活动是否结束

            that.e = xGame.loadPopTmpl('valentine');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                valentinepop.hide();
                e.preventDefault();
            });
            xGame.updateCd('valentine', that.resData.cd_time);
            that.leftDom = that.e.find('#valentine_left');
            that.rightDom = that.e.find('#valentine_right');
            /* 填充元宝总数 */
            that.e.find('#valentine_gold_num').html(that.getWan(res.all_gold));
            /* 填充左侧数据 */
            that.playerTopList(that.resData.rank_list);
            that.leftReward(that.resData.my_rank);
            /*填充右侧奖品以及数据*/
            that.fillRightData(that.resData.list);
            
            //填充活动时间
            //this.rightDom.find('#valentine_cd').html(helper.timeToString(that.resData.cd_time));
            that.timer = setTimeout(function() {
                that.updatepage();
            }, 900000);
            that.pos = 0;
            that.bindAll();

            that.e.show();
            that.isshow = true;
            $('#worldloading').hide();
        },
        //玩家排名列表
        playerTopList: function(list) {
            var html ='';
            var that = this;
            //构建列表
            for(var i = 0; i < list.length; i++){
                 //判断奖励物资类型
                var gettype = list[i].type;
                var reward = list[i].num;
                var total_gold = that.getWan(list[i]["total_gold"]);
                var color = '';
                if(list[i].info && list[i].info.quality || list[i].type == 12) {
                    color = helper.getItemColor(list[i].info.quality);
                    reward = '';
                }
                //构建列表，判断是否是自己
                html += '<li uid="' + i + '" class="valentine_user_list_li '+(list[i]["uid"] == player.info.uid ? 'yellow' : '')+'"><em class="top">' + (i + 1) + '</em><em class="nickname" >' + list[i].nickname + '</em><em class="gold">' + total_gold + '</em>';
                if (gettype == 8) {    //装备
                    html += '<em class="reward rtype' + list[i].type  + '" style="background-image: url(' + xGame.itemurl(list[i]["info"].id) + ');"></em>';
                    html += '<em class="reward text">' + reward+ '</em>';
                } else if (gettype == 12) { //武将
                    html += '<em class="reward avatar rtype' + list[i].type  + '" style="background-image: url(' + xGame.avatar(list[i]["info"].image) + ');"></em>';
                    html += '<em class="reward text">' + reward+ '</em>';
                } else if (gettype == 26) { //坐骑
                    html += '<em class="reward horse rtype' + list[i].type  + '" style="background-position:' + helper.getHorsePostion('buff', list[i]["info"].id) + ';"></em>';
                    html += '<em class="reward text">' + reward+ '</em>';
                }  else if (gettype >= 17 && gettype >= 25) { //精华
                    html += '<em class="essence rtype' + list[i].type  + '" style="background-image:url(' + xGame.essence(list[i]["info"].id) + ');"></em>';
                    html += '<em class="reward text">x' + list[i].num+ '</em>';
                } else if (gettype == 0) {
                    continue;
                } else {
                      html += '<em class="reward rtype' + list[i].type  + '" ></em>'; 
                      html += '<em class="reward text">x' + reward+ '</em>';
                }
            }
            that.leftDom.find('#valentinepop_list').html(html);

            if(that.rewardScroller == null) {
                that.rewardScroller = new iScroll('valentine_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });       
            } 
            that.isTimeout.push(setTimeout(function() {
                that.rewardScroller.refresh();
            }, 200));
        },
        leftReward: function(my_rank) {
            var that = this;
            var rewardContent = that.leftDom.find('.valentine_reward_centent');
            var rewardBtn = that.leftDom.find('#valentine_reward_btn');
            var html = '';
            if(my_rank.is_reward == 0 ){
                rewardBtn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('warena_fetch'));    
            } else if(my_rank.is_reward == 1){
                rewardBtn.removeClass('pop_btn_middle_disable').addClass('pop_btn_middle').html(lang('warena_fetch'));
            } else if(my_rank.is_reward == 2){
                rewardBtn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('warena_have_fetch'));
            } 
            if(my_rank.rank){
                html += "<span class='reward_title'>" + lang('qingminpop_current_rank') + "<em>" + my_rank.rank + "</em></span>";
                var gettype = my_rank.type;
                if(gettype == 26){ //坐骑
                    html += '<span  class="reward_rank  rtype' + my_rank.type + '"  style="background-position:' + helper.getHorsePostion('buff', my_rank.info.id) + ';"><em class="text">' + my_rank.info.name + '</em></span>';
                } else if(gettype == 12){  //武将  12
                    html += '<span class="reward_rank avatar rtype' + my_rank.type + '" style="background-image: url(' + xGame.avatar(my_rank.info.image) + ');"><em class="text">' + my_rank.info.name + '</em></span>';
                } else if(gettype == 8){//装备 8
                    html += '<span class="reward_rank item rtype' + my_rank.type + '"  style="background-image: url(' + xGame.itemurl(my_rank.info.id) + ');"><em class="text">' + my_rank.info.name + '</em></span>';
                } else if(gettype >= 17 && gettype <= 25){  //精华
                    html += '<span  class="reward_rank essence rtype' + my_rank.type + '" style="background-image:url(' + xGame.essence(my_rank.info.id) + ');"><em class="text">' + my_rank.info.name + '</em></span>';
                } else{
                    html += '<span  class="reward_rank  rtype' + my_rank.type + '" ><em class="text">x' + my_rank.num + '</em></span>';
                }      
            } else {
                html += "<span class='reward_title'>" + lang('qingminpop_current_rank') + "</span>";
                html += '<span class="no_rank">' + lang('beatengod_not_reach') + '</span>';
            }
            rewardContent.html(html);
        },
        fillRightData: function(list) {
            var html ='';
            var that = this;
            //构建列表
            for(var i = 0 ; i < list.length ; i++){
                html += '<li id="valentgift_l' + list[i].id + '" uid="' + i + '">';
                if(list[i].is_re == 0){
                    html += "<button class='btn pop_btn_small_disable'>" + lang('not_reached') + "</button>";
                } else if(list[i].is_re == 1){
                    html += "<button class='btn pop_btn_small' uid = " + list[i].id + ">" + lang('receive') +"</button>";
                } else{
                    html += "<button class='btn pop_btn_small_disable'>" + lang('warena_have_fetch') + "</button>";
                }
                html += "<div class='title'>" + lang('valentinepop_gift_level',[ list[i].gold]) + "</div>"; 
                html += this.buildRewardHtml(list[i]);
                html += "</li>";
            }
            that.rightDom.find('#valentinepop_reward_list').html(html);
            if(that.rewardScroller2 == null) {
                that.rewardScroller2 = new iScroll('valentine_reward_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });       
            }
            that.rewardScroller2.refresh();
        },
        getListImg: function(type) {
            var className;
            switch(parseInt(type)) {
            case 8://装备
            case 11://坐骑
            case 13://武将
                className = 'text';
                break;
            case 1://元宝
                className = 'add_gold';
                break;
            case 2://银币
                className = 'add_silver';
                break;
            case 3://黄宝石
                className = 'add_get2';
                break;
            case 4://紫宝石
                className = 'add_get3';
                break;
            case 5://原魂
                className = 'add_soul';
                break;
            case 6://水晶
                className = 'add_jade';
                break;
            case 7://声望
                className = 'add_prestige';
                break;
            case 9://玉石碎片
                className = 'add_gem_part';
                break;
            case 10://突飞卡
                className = 'add_tfcard';
                break;
            case 12://蓝宝石
                className = 'add_get1';
                break;
            case 14://军令
                className = 'add_junling';
                break;
            case 15://精炼石
                className = 'add_refinestone';
                break;
            case 16://五彩石
                className = 'add_colorstone';
                break;
            case 17://上古血玉
                className = 'add_bloodstone';
                break;
            case 18://装备精华
                className = 'essence';
                break;
            }
            return className;
        },
        buildRewardHtml: function(data) {
            var html = '';
            var gettype = 0;
            for (var i = 1; i <= 5; i ++) {
                gettype = parseInt(data['type' + i]);
                if (gettype == 8) {    //装备
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '" fid="' + i + '">';
                    html += '<span class="item" style="background-image: url(' + xGame.itemurl(data['info' +i].id) + ');"></span>';
                    html += '<b class=' + (data['info' +i].name.length == 5 ? '"indent"' : '""') + 'style="color: ' + helper.getItemColor(data['info' +i].quality) + '">' + data['info' +i].name + '</b>';
                    html += '</div>';
                } else if (gettype == 12) { //武将
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '" fid="' + i + '">';
                    html += '<span class="avatar" style="background-image: url(' + xGame.avatar(data['info' +i].image) + ');"></span>';
                    html += '<b style="color: ' + helper.getItemColor(data['info' +i].quality) + '">' + data['info' +i].name + '</b>';
                    html += '</div>';
                } else if (gettype == 26) { //坐骑
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '" fid="' + i + '">';
                    html += '<span  style="background-position:' + helper.getHorsePostion('small', data['info' +i].id) + ';"></span>';
                   // html += '<b style="color: ' + helper.getItemColor(data['info' +i].quality) + '">' + data['info' +i].name + '</b>';
                   html += '<b>x' + data['num' + i] + '</b>';
                    html += '</div>';
                }  else if (gettype >= 17 && gettype <= 25) { //精华
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '" fid="' + i + '">';
                    html += '<span  class="essence" style="background-image:url(' + xGame.essence(data['info' +i].id) + ');"></span>';
                   // html += '<b style="color: ' + helper.getItemColor(data['info' +i].quality) + '">' + data['info' +i].name + '</b>';
                    html += '<b>x' + data['num' + i] + '</b>';
                    html += '</div>';
                } else if (gettype == 0) {
                    continue;
                } else {
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '" >';
                    html += '<span></span>';
                    html += '<b>x' + data['num' + i] + '</b>';
                    html += '</div>';
                }
            }
            return html;
        },
        getReward: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var id = that.resData.my_rank.rank - 1;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case 1:
                    that.leftDom.find('#valentine_reward_btn').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('warena_have_fetch'));            
                    that.showResultMsg(that.resData.rank_list[id],res.all_gold,res.all_silver);
                    break;
                default:
                    xGame.toast.show(res.msg);
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'act_tanabata',
                'm': 'rank_reward',
            },
            Func);
            Func = null;
        },
        showResultMsg: function(info,gold,silver) {
            var that = this;
            switch (parseInt(info.type)) {
            case 1:
                player.updateinfo({
                    'gold': parseInt(gold),
                });
                xGame.toast.show(lang('c_you_get_gold', [info.getvalue]));
                break;
            case 2:
                player.updateinfo({
                    'silver': parseInt(silver)
                });
                xGame.toast.show(lang('c_you_get_silver', [info.num]));
                break;
            case 3://黄宝石
                xGame.toast.show(lang('c_you_get_gem2', [info.num]));
                break;
            case 4://紫宝石
                xGame.toast.show(lang('c_you_get_gem3', [info.num]));
                break;
            case 5://原魂
                xGame.toast.show(lang('c_you_get_soul', [info.num]));
                break;
            case 6://玉石
                xGame.toast.show(lang('c_you_get_stone', [info.num]));
                break;
            case 7://声望
                xGame.toast.show(lang('c_you_get_prestige', [info.num]));
                break;
            case 8://装备
                xGame.toast.show(lang('c_you_get_item', [info.info.name]));
                break;
            case 9://宝石碎片
                xGame.toast.show(lang('c_you_get_gemworld_part', [info.num]));
                break;
            case 10://突飞卡
                xGame.toast.show(lang('c_you_get_tfcard', [info.num]));
                break;
            case 26://坐骑
                xGame.toast.show(lang('c_you_get_mount', [info.info.name]));
                break;
            case 11://蓝宝石
                xGame.toast.show(lang('c_you_get_gem1', [info.num]));
                break;
            case 12://武将
                miscpop.newhero(info.info);
                break;
            case 14://军令
                xGame.toast.show(lang('c_you_get_act', [info.num]));
                player.updateinfo({
                    'act':
                    parseInt(info.all_act)
                });
                break;
            case 14://精炼石
                xGame.toast.show(lang('acttreasurepop_msg14', [info.num]));
                break;
            case 15://五彩石
                xGame.toast.show(lang('acttreasurepop_msg15', [info.num]));
                break;
            case 16://上古血玉
                xGame.toast.show(lang('acttreasurepop_msg16', [info.num]));
                break;
            case 18://装备精华
                xGame.toast.show(lang('acttreasurepop_msg18', [info.getvalue.name, xGame.essence(info.getvalue.id), info.getvalue.value]));
                break;
            }
            xGame.unlock();
        },
        bindAll: function() {
            var that = this;
            that.leftDom.undelegate('#valentine_reward_btn.pop_btn_middle', 'touchend').delegate('#valentine_reward_btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                } 
                that.getReward();
            });
            that.leftDom.undelegate('.nickname', 'click').delegate('.nickname', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                } 
                xGame.showpop('info', parseInt($(this).attr('uid')));
            });
            if(that.isEnd == 0) { //活动是否结束
                that.rightDom.undelegate('#valentine_start', 'touchend').delegate('#valentine_start', 'touchend',
                function() {
                    if (xGame.checkLock(true) || $(this).hasClass('disable')) { //不设置属性
                        return false;
                    }
                    that.startLottery();
                });           
            }
            that.e.find('#valentinepop_reward_list').undelegate('.pop_btn_small', 'click').delegate('.pop_btn_small', 'click',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
               that.receiveGift(parseInt($(this).attr('uid')));
                $(this).removeClass('pop_btn_small').addClass('pop_btn_small_disable').html(lang('warena_have_fetch'));
            });
            that.e.find('#valentinepop_reward_list').undelegate('li div', 'click').delegate('li div', 'click', function (e) {  
                if ($(this).hasClass('rtype12')) {
                   var index = ($(this).parent().attr('uid'));  // 取得武将在所有奖励中的索引
                   var fid = ($(this).attr('fid')); // 取得奖励的索引
                       miscpop.showHeroInfo(that.resData.list[index]['info'+fid]);
                } else if ($(this).hasClass('rtype8')) {
                    var index = ($(this).parent().attr('uid')); // 取得装备在所有奖励中的索引
                    var fid = ($(this).attr('fid')); // 取得奖励的索引
                        miscpop.showItemInfo(that.resData.list[index]['info'+fid]);
                }else if ($(this).hasClass('rtype26')) {
                    var index = ($(this).parent().attr('uid')); // 取得奖励的索引
                    var fid = ($(this).attr('fid')); // 取得奖励的索引
                        miscpop.showMarchInfo(that.resData.list[index]['info'+fid]);
                }
                e.preventDefault();
                e.stopPropagation();
            });
            that.e.find('#valentine_left').undelegate('span.reward_rank', 'click').delegate('span.reward_rank', 'click', function (e) {  
                if ($(this).hasClass('rtype12')) {
                       miscpop.showHeroInfo(that.resData.my_rank.info);
                } else if ($(this).hasClass('rtype8')) {
                        miscpop.showItemInfo(that.resData.my_rank.info);
                }else if ($(this).hasClass('rtype26')) {
                        miscpop.showMarchInfo(that.resData.my_rank.info);
                }
                e.preventDefault();
                e.stopPropagation();
            });
            that.e.find('#valentinepop_list').undelegate('li em', 'click').delegate('li em', 'click', function (e) {  
                if ($(this).hasClass('rtype12')) {
                   var index = ($(this).parent().attr('uid'));  // 取得武将在所有奖励中的索引
                       miscpop.showHeroInfo(that.resData.rank_list[index].info);

                } else if ($(this).hasClass('rtype8')) {
                    var index = ($(this).parent().attr('uid')); // 取得装备在所有奖励中的索引
                        miscpop.showItemInfo(that.resData.rank_list[index].info);

                }else if ($(this).hasClass('rtype26')) {
                    var index = ($(this).parent().attr('uid')); // 取得坐骑奖励的索引
                        miscpop.showMarchInfo(that.resData.rank_list[index].info);

                }else if ($(this).hasClass('nickname')) {
                    var index = ($(this).parent().attr('uid')); // 取得宝石在所有奖励中的索引
                        xGame.showpop('info',that.resData.rank_list[index].uid);

                }else if ($(this).parent().hasClass('type27')) {
                    var index = $('#interpop_info_dom .box').index($(this).parent()); // 取得将魂在所有奖励中的索引
                        miscpop.showSoulInfo(self.rewardData[index].info);
                }
                e.preventDefault();
                e.stopPropagation();
            });
            that.e.find('#valentine_right').undelegate('#valentine_bird_click', 'click').delegate('#valentine_bird_click', 'click', function (e) {  
                     miscpop.showMarchInfo(that.resData.mount);
                e.preventDefault();
                e.stopPropagation();
            });

        },
        receiveGift: function(id) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        if (that.anim) {
                            that.hideAnim();
                        }

                        if (that.clearAnim) {
                            clearTimeout(that.clearAnim);
                        }
                        
                        that.e.find('.valentine_reward_box').remove();
                        
                        if (that.deleteThread) {
                            clearTimeout(that.deleteThread);
                        }

                        that.deleteThread = null;
                        
                        player.updateinfo({
                            'gold' : res.all_gold,
                            'act' : res.all_act,
                            'silver' : res.all_silver
                        });
                        var reward = that.resData.list;
                        for (var i = 0; i < reward.length; i++) {
                            if (reward[i].id == id) {
                                that.rewardDown(reward[i]);
                            }

                        }

                        that.playAnim(that.e.find('#valentinepop_reward_list').find('#valentgift_l' + id));

                        that.clearAnim = setTimeout(function () {
                            that.hideAnim();
                        }, 3000);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'act_tanabata',
                'm': 'reward',
                'id': id
            },
            Func);
            Func = null;
        },
        playAnim: function(obj) {
            var that = this;
            var length = obj.find('.reward').length;
            var xy_config = {
                1 : [21, 22],
                2 : [108, 22],
                3 : [195, 22],
                4 : [282, 22],
                5 : [369, 22]
            };
            that.anim = [];
            for (var i = 1; i <= length; i++) {
                that.anim[i] = new movieClipClass({
                    "img": staticUrl + 'img/pop/firstpaypop_anim.png',
                    "width": 93,
                    "height": 90,
                    "x": xy_config[i][0],
                    "y": xy_config[i][1],
                    "loop": true,
                    "frames": [{
                        rect: [0, 0],
                        interval: 100
                    },
                    {
                        rect: [93, 0],
                        interval: 100
                    },
                    {
                        rect: [186, 0],
                        interval: 100
                    },
                    {
                        rect: [279, 0],
                        interval: 100
                    },
                    {
                        rect: [0, 90],
                        interval: 100
                    },
                    {
                        rect: [93, 90],
                        interval: 100
                    },
                    {
                        rect: [186, 90],
                        interval: 100
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

            for (var i = 1; i <= 5; i++) {
                if (this.anim[i]) {
                    this.anim[i].destory();
                }
            }
            this.anim = null;
        },

        rewardDown: function(data) {
            var html = '';
            var gettype = 0;
            html += '<div class="valentine_reward_box">';

            for (var i = 1;i <= 5; i ++) {
                gettype = parseInt(data['type' + i]);
                if (gettype == 8) {   //装备
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em class="item" style="background-image: url(' + xGame.itemurl(data['info' +i].id) + ');"></em>';
                    html += '<b style="color: ' + helper.getItemColor(data['info' +i].quality) + '">' + data['info' +i].name + '</b>';
                    html += '</span>';
                } else if (gettype == 12) {   //武将
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em class="avatar" style="background-image: url(' + xGame.avatar(data['info' +i].image) + ');"></em>';
                    html += '<b style="color: ' + helper.getItemColor(data['info' +i].quality) + '">' + data['info' +i].name + '</b>';
                    html += '</span>';
                } else if (gettype >= 17 && gettype <= 25) {   //精华
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em  class="essence" style="background-image:url(' + xGame.essence(data['info' +i].id) + ');"></em>';
                    html += '<b>x' + data['num' +i] + '</b>';
                    html += '</span>';
                } else if (gettype == 26) { //坐骑
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em  style="background-position:' + helper.getHorsePostion('small', data['info' +i].id) + ';"></em>';
                    html += '<b>x' + data['num' +i] + '</b>';
                    html += '</div>';
                } else if (gettype == 0) {
                    continue;
                } else {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '"><em></em>x' + data['num' + i] + '</span>';
                }
            }

            html += '</div>';
            this.e.find('#valentinepop_wrap').append(html);
            
            this.deleteThread = setTimeout(function() {
                
                if (valentinepop.e) {
                    valentinepop.e.find('.valentine_reward_box').remove();
                }

            }, 3000);
            
            html = null;
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
        
        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.leftDom) {
                this.leftDom.undelegate('#valentine_reward_btn.pop_btn_middle', 'touchend');
                this.leftDom.undelegate('.nickname', 'click');
                this.leftDom.remove();
                this.leftDom = null;
            }
            if (this.rightDom) {
                this.rightDom.undelegate('#valentine_start', 'touchend');
                this.rightDom.remove();
                this.rightDom = null;
            }
        },
        
        hide: function() {
            xGame.unlock();
            this.isshow = false;

            xGame.updateCd('valentine', 0);
            
            if (this.rewardScroller != null) {
                this.rewardScroller.destroy();
                this.rewardScroller = null;
            }
            if (this.rewardScroller2 != null) {
                this.rewardScroller2.destroy();
                this.rewardScroller2 = null;
            }
            this.box = null;
            this.giftData = null;
            this.leftDom = null;
            this.rightDom = null;
            this.resData = null;
            this.pos = null;
            this.lotteryList = null;
            this.lastExtreme = null;
            this.isEnd = null;
            this.unbindAll();
            if (this.timer) {
                clearTimeout(this.timer);
                this.timer = null;
            }
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;            
        },
    };
})();