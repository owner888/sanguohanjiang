var player;
var xGame;
var timer; //核心计时器
var gAudio; //声音

/* 游戏主模块 ___World */
var loginWorld, cityWorld, mapWorld, battleWorld, roleWorld, treasureWorld, countrybattleWorld, countrymapWorld, soulWorld, mineWorld, countryWorld, bossWorld, towerWorld, generaltaskWorld, elitebattleWorld, championsWorld, palaceheroWorld, countrybossWorld, herothroneWorld, gemWorld, sanctumWorld, gvgWorld, gvgbattleWorld, countrymineWorld, nianWorld, heavenWorld, warWorld, warheroWorld,  breakthroughworld;

/* 功能模块(弹窗) ___pop */
var miscpop, packpop, strongpop, levypop, musterpop, fosterpop, heropop, teampop, tradepop, barpop, shoppop, practicepop, chatpop, lotterypop, vippop, mailpop, arenapop, settingspop, rushpop, mailboxpop, giftpop, sacrificepop, activitypop, iospaypop, firstpaypop, exercisepop, bossbandpop, drinkpop, taskgiftpop, giftchangepop, dailytaskpop, dailytradepop, infopop, treasuremappop, talentpop, dailysignpop, worshippop, rebatepop, helpinfopop, workshoppop, slyzpop, expostulationpop, countrypop, countryhousepop, countryworshippop, elitepop, strategypop, inheritpop, trendspop, blackmarketpop, worldarenapop, countrybanquetpop, countrylearnpop, christmaspaypop, christmassignpop, christmasgeneralpop, yuandansignpop, heromanualpop, godminepop, newyearpaypop, eggpop, newbiegift, overseastradepop, newyeardinnerpop, beatengodpop, friendspop, smashpop, epicpop, bearpop, viprewardpop, countrydicepop, qingmingpop, limitedgodpop, binding91pop, advancedpop, invitationpop, missionpop, sacredtreepop, anniversarypop, countrytreasurepop, mountpop, studfarmpop, zhulupop, practiceleappop, christmasgiftpop, ndgiftpop, viprepaypop, springshoppop, newyearlotterypop, firstpaygiftpop, levelgiftpop, newgiftpop, loginedpop, regrewardpop, countrynoticepop, countrywarnoticepop, equiprefinepop, refineinheritpop, refineachievepop, strongachievepop, acttreasurepop, championsrewardpop, recallpop, scoreshoppop, showmarpop, kemaripop, kemarisuccpop, kemarilosepop, worldcuppop, newpassrewardpop, chargegiftpop, signinpop, outprintgodpop, valentinepop, beautypop, lordsoulpop, soulrefinepop, countrycollegepop, midautumnpop, nationcelpop, loginrewardpop, paycheckspop, countrymallpop, nationaldaypop, halloweenpop, systempop, forgepop, resolvepop, pacifypop, sweeppop, enrichmentpop, sevenstarpop, christmasbuypop;

/* 新手引导 */
var guide = new guideClass;

/* CD */
var CD = {};


$(document).ready(function() {

    //document.addEventListener('touchstart', function (e) { window.scrollTo(0, 1); }, false);
    if (typeof preventTouchMove == 'undefined') {
        document.addEventListener('touchmove',
        function(e) {
            e.preventDefault();e.stopPropagation();
            return false;
        },
        false);
    }

    hideAddressBar();

    xGame = new xGameClass;
    xGame.init();
    stopLoadingAnim = true; //停止动画

    // 发送用于统计激活的信息
    if(typeof cordova != 'undefined') {
        iosTrackData();
    }

});

function getDeviceInfo(next) {
    Cordova.exec(function(deviceInfo) {

        xGame.devicetoken = deviceInfo.devicetoken;
        xGame.adid = deviceInfo.idfa;
        xGame.ate = deviceInfo.idfaEnable;
        xGame.udid = deviceInfo.openudid;
        xGame.macaddr = deviceInfo.mac;
        xGame.odin = deviceInfo.odin;

        if(next) {
            next(deviceInfo);
        }

    }, function() {}, "CDVinfo", "getallInfo");
}
function iosTrackData() {

    getDeviceInfo(function() {

        sendTrackData();

    });

}

function sendTrackData() {

    var isTracked = localStorage['sg_tracked_' + client_version] ? true : false;
    if(isTracked == false) {

        var gourl = UCAPI + '&c=iostrack&m=trackall&advertising_id='+xGame.adid+'&ate='+xGame.ate+'&openudid='+xGame.udid+'&mac='+xGame.macaddr+'&odin='+xGame.odin+'&channel='+channelID;
        console.log(gourl);
        var params = {
            'type' : 'get',
            'dataType' : 'json',
            'url' : gourl,
            'success' : function(data) {
                //不处理
            },
        };
        $.ajax(params);

        localStorage['sg_tracked_' + client_version] = 'true';
    }  

}