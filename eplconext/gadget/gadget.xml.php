<?php
/* In Apache rewritten, so $_GET['r'] contains the original (pre-rewritten) filename 
 * 
 * How should we represent:
 * 1) use native SURFconext gadget
 * 2) use local OpenSocial.getGroups for retrieving groups
 * 3) use external OpenSocial.getGroups for retrieving groups (3-legged-oauth) 
 */

// print XML-headers here // ------------------------------------------------
header ("Content-Type:text/xml");
print '<?xml version="1.0" encoding="UTF-8" ?>';
?>
<Module>
<ModulePrefs title="EtherpadLite" scrolling="true" height="800"
             author="mdobrinic"
             author_email="info@cozmanova.com"
             description="Etherpad Lite GroupPad gadget">
    <Require feature="opensocial-2.5" />
    <Require feature="opensocial-data" />
    <Require feature="dynamic-height"/>
    <Require feature="osapi" />
    <Require feature="locked-domain"/>
    <Require feature="views" />
    <Require feature="setprefs" />
    <OAuth>
        <Service name="EPLconext">
            <Access url="https://etherpad-groups.identitylabs.org/simplesaml/module.php/oauth/accessToken.php" method="GET" />
            <Request url="https://etherpad-groups.identitylabs.org/simplesaml/module.php/oauth/requestToken.php" method="GET" />
            <Authorization url="https://etherpad-groups.identitylabs.org/simplesaml/module.php/oauth/authorize.php" />
        </Service>
    </OAuth>
</ModulePrefs>
<UserPref name="padparam" datatype="hidden" />
<Content type="html" view="default">
<![CDATA[
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js"></script>
<script src="https://etherpad-groups.identitylabs.org/eplconext/gadget/popup.js"></script>
<script src="https://etherpad-groups.identitylabs.org/eplconext/gadget/h.js"></script>
<script src="https://etherpad-groups.identitylabs.org/eplconext/gadget/gs.js"></script>
<link rel="stylesheet" type="text/css" href="https://etherpad-groups.identitylabs.org/eplconext/css/eplgadget.css" />

<div id="splash">
    &nbsp;
</div>

<div id="content">
<div id="etherpadbar">
    &nbsp;
</div>
<div id="main" style="display: none"></div>
<div id="approval" style="display: none">
    <p>Give this gadget permission to use your personal and group information
        with Etherpad. Without this permission it is not possible to start and
        share pads. Your permission will be remembered for this gadget.</p>
    <a href="#" id="personalize">Authorise gadget.</a>
</div>
<div id="waiting" style="display: none">Please click <a href="#" id="approvaldone">I've approved access</a>
    once you've approved access to your personal and group information.</div>

<div id="messagebox">
    <div id="mbox_title">
        _
    </div>
    <div id="mbox_description">
        _
    </div>
</div>

<script type="text/javascript">

// duplicated in each view:

var gadgCtx = {
    epl_baseurl: 'https://etherpad-groups.identitylabs.org/eplconext/',
    portal_baseurl: top.location.origin
}

var user_id=''; // placeholder for user_id
var groupcontext='';  // placeholder for groupcontext
var groupname='';  // placeholder for groupname
var currentGroup='';  //placeholder for currentGroup

function clog(message) {
    console.log("(*) Etherpad-Groups: " + message);
}

function get_current_group() {
    return currentGroup;
}

// Helper for UI, facilitating OAuth setup
function showOneSection(toshow) {
    var sections = [ 'main', 'approval', 'waiting'];
    for (var i=0; i < sections.length; ++i) {
        var s = sections[i];
        var el = document.getElementById(s);
        if (s === toshow) {
            el.style.display = "block";
        } else {
            el.style.display = "none";
        }
    }
}

// delete pad
// Invoke makeRequest() to fetch a token that authorizes access to a given pad
// OAuth has been setup by now because fetchData does this.
function deletePad(groupname, padid, onsuccessfunction, xtra_argument) {
    var params = {};
    var url = gadgCtx.epl_baseurl+'padmanager.php/remove/'+ encodeURIComponent(groupname) + '/' + encodeURIComponent(padid);

    clog('Removing Pad using URL: ' + url);
    params[gadgets.io.RequestParameters.CONTENT_TYPE] = gadgets.io.ContentType.JSON;
    params[gadgets.io.RequestParameters.AUTHORIZATION] = gadgets.io.AuthorizationType.OAUTH;
    params[gadgets.io.RequestParameters.OAUTH_SERVICE_NAME] = "EPLconext";
    params[gadgets.io.RequestParameters.METHOD] = gadgets.io.MethodType.GET;
    gadgets.io.makeRequest(url, function (response) {
        if (response.oauthApprovalUrl) {
            alert('OAuth not yet setup; flow error?');
            return;
        }
        if (response.data) {
            if (response.data.result == "ERROR") {
                alert('Only owner can delete a pad.');
            } else {
                var j = response.data.data;
                onsuccessfunction(xtra_argument, j.padId);
            }

        } else {
//            console.log('text/data:' + response.text + '-/-' + response.data);
        }
    }, params);

}

function callAddPad(groupname, newpadname, onsuccessfunction, xtra_argument) {
    var params = {};
    var url = gadgCtx.epl_baseurl + 'padmanager.php/remoteadd/' + encodeURIComponent(groupname) + '/' + encodeURIComponent(newpadname);

    params[gadgets.io.RequestParameters.CONTENT_TYPE] = gadgets.io.ContentType.JSON;
    params[gadgets.io.RequestParameters.AUTHORIZATION] = gadgets.io.AuthorizationType.OAUTH;
    params[gadgets.io.RequestParameters.OAUTH_SERVICE_NAME] = "EPLconext";
    params[gadgets.io.RequestParameters.METHOD] = gadgets.io.MethodType.GET;

    gadgets.io.makeRequest(url, function (response) {
        clog(response);
        if (response.oauthApprovalUrl) {
            alert('OAuth not yet setup; flow error?');
            return;
        }

        if (response.data) {
            var j = response.data.data;
            onsuccessfunction(xtra_argument, j.padId);
        }
    }, params);
}

// display [all teams]/[currentGroup]
function showHeader(allowTeamChange) {
    var dh = cozmanovaHelper.createElementWithAttributes('div', {});
    if (allowTeamChange) {
        var allteams = cozmanovaHelper.createElementWithAttributes('a', { 'href':'#' });
        allteams.appendChild( document.createTextNode('All teams') );
        allteams.onclick=function(){ groupSelector.clearGroup(); };

        dh.appendChild(allteams);
    }

    dh.appendChild(document.createTextNode(' > '));
    dh.appendChild(document.createTextNode(groupname));

    document.getElementById("main").appendChild(dh);
} //showHeader


function jQInit() {
    $(".cPadLinkAdd").click(function() {
        // groupname:
        var groupfromlinkid=$(this).attr('id');
        var theid = groupfromlinkid.substr(3);
        theid = decodeURI(theid);

        // container of group pads:
        var linkul = this.parentNode.parentNode;

        var padname = prompt("Name for new pad in the group " + groupname);
        if (/[;\/\\\?:@&=+\$,{}\^\[\]`|]/.test(padname) || padname == null) {
            alert("Your pad name cannot contain the following characters: ;\?:@&=+\$,{}\^\[\]`|")
        } else {
            if (padname.length > 0) {

                // AJAX-call
                callAddPad(theid, padname, function(container_element, padId) {
                    var padname;
                    p = padId.split('$');
                    if (p.length==1) { padname=p[0]; } else { padname=p[1]; }

                    var pad = {'name' : padname,
                        'group_id' : p[0]};
                    var newpadli = createNewPadNode(pad, linkul);

                    var c = container_element.children;
                    var i = c.length;
                    container_element.insertBefore(newpadli, c[i-1]);

                    // unbind click handlers before re-setting for new element
                    $(".padhandled").unbind("click");

                    // disable no-pads-available:
                    var elnodocs=document.getElementById('elnodocs');
                    if (elnodocs) {
                        elnodocs.style.display = "none";
                    }

                    jQInit();
                    gadgets.window.adjustHeight();
                }, linkul); // callAddPad
            } else {
                alert("Invalid padname");
            }
        }
    });
} // jQInit()

// helper:
function createNewPadNode(pad,linkul) {
    var s = pad.name;
    var liNode = document.createElement('li');
    var imgNode = cozmanovaHelper.createElementWithAttributes('img', {
        'src': gadgCtx.epl_baseurl + 'images/arrownext01.png',
        'height':'12px', 'style': 'margin-right:5px;'});
    liNode.appendChild(imgNode);

    var a = cozmanovaHelper.createElementWithAttributes('a', { 'href' : '#', 'class' : 'padnode' } );
    a.appendChild(document.createTextNode(s));

    a.onclick = function() {
        nw = window.open();
        authorizeCanvasPad(pad.group_id+'$'+pad.name, nw);
    }
    liNode.appendChild(a);
    var removeImgNode = cozmanovaHelper.createElementWithAttributes('img', {
        'src': gadgCtx.epl_baseurl + 'images/redcross.png',
        'height':'12px', 'style': 'margin-left: 10px;'});
    removeImgNode.onclick = function() {
        // always: grouppad, so construct FQ padname:
        if(confirm("Confirm that you want to delete PAD: " + pad.name)) {
            deletePad(groupname, pad.group_id + '$' + pad.name, function(container_element, padId) {
                fetchData();
            },linkul);
        }
    }
    liNode.appendChild(removeImgNode);
    return liNode;
}

// process the json datastructure:
// {
// "result":'OK',
// "group":'groupname',
// "data":
// [
// {
// 'name' : 'pad-name',
// 'url' : 'pad-url',
// 'created' : 'pad-created-unix-timestamp',
// 'owner' : 'pad-owner',
// 'group_id' : 'pad-group-id' }
// ]
// }
// result is either 'OK','NOGROUP','ERROR'
function showList(result) {
    clog("Processing list.");
    var l = '- unprocessed list -';
    var mainList = document.getElementById("main");
    if (mainList) {
        mainList.innerHTML = "";
    }

    var headerElement = cozmanovaHelper.createElementWithAttributes('h3', {
        'id' : 'mainList'
    });
    var nameNode;

    if (result.data.result=='ERROR') {
        nameNode = document.createTextNode('Error occurred.');
    } else if (result.data.result=='NOGROUP') {
        nameNode = document.createTextNode('Tab is not assigned to a team.');
    } else {
        nameNode = document.createTextNode('Select pad to edit this document in a maximized gadget window');
    }

    headerElement.appendChild(nameNode);
    document.getElementById("main").appendChild(headerElement);

    if (! (result.data.data instanceof Array)) {
        var t = document.createTextNode('Invalid input from service');
        document.getElementById("main").appendChild(t);
    } else {
        var pad;
        var listNode = cozmanovaHelper.createElementWithAttributes('ul', {
            'style' : 'list-style: none;'
        });

        if (result.data.data.length > 0) {
            for(var i = 0; i < result.data.data.length; i++) {
                pad = result.data.data[i];
                padNode = createNewPadNode(pad);
                listNode.appendChild(padNode);
            }
        } else {
            var elnodocs = cozmanovaHelper.createElementWithAttributes('i', {
                'id' : 'elnodocs', 'style' : 'display: block'});
            elnodocs.appendChild(document.createTextNode(
                'No Etherpad documents are available.'
            ));
            document.getElementById("main").appendChild(elnodocs);
        }
        // append "Add Pad"-link to list:
        // <li><hr/><a class="cPadLinkAdd padhandled" id="apg{$groupid}" href="#" alt="Add new pad"><img src="images/greenplus.png" height="12px" />&nbsp;New pad</a></li>
        var addPadLink=cozmanovaHelper.createElementWithAttributes('a', {
            'class':'cPadLinkAdd padhandled',
            'id':'apg'+groupcontext,
            'href':'#',
            'alt':'Add new pad'});
        addPadLink.appendChild( document.createTextNode('Add new pad') );
        var addPadLinkItem=cozmanovaHelper.createElementWithAttributes('li', {});
        addPadLinkItem.appendChild( cozmanovaHelper.createElementWithAttributes('img', {
            'src': gadgCtx.epl_baseurl + 'images/greenplus.png',
            'height':'12px', 'style': 'margin-right:5px;'}) );

        addPadLinkItem.appendChild(addPadLink);
        listNode.appendChild(addPadLinkItem);
        // continue ...

        document.getElementById("main").appendChild(listNode);
    }
}

function showBigMessage(msg, styleclass) {
    var el = document.createElement('p');
    if (styleclass) {
        el.setAttribute('class', styleclass);
    }
    el.appendChild( document.createTextNode( msg ) );
    document.getElementById('main').appendChild(el);
}

// Set global groupname and then resume execution with f
function doWithGroupname(f) {
    groupcontext = currentGroup;
    groupname = currentGroup;
    f();
    var p = {userId:'@owner', groupId: groupcontext};
} // doWithGroupname()

// Invoke makeRequest() to fetch data from the service provider endpoint.
// Depending on the results of makeRequest, decide which version of the UI
// to ask showOneSection() to display. If user has approved access to his
// or her data, display data.
// If the user hasn't approved access yet, response.oauthApprovalUrl contains a
// URL that includes a Google-supplied request token. This is presented in the
// gadget as a link that the user clicks to begin the approval process.

function fetchData() {
    var params = {};
    url = gadgCtx.epl_baseurl+'padmanager.php/grouppadlist/'+escape(groupcontext);

    // append current group identifier to the request
    url = cozmanovaHelper.addToUrl(url, 'nocachething', new Date().getTime());

    params[gadgets.io.RequestParameters.CONTENT_TYPE] = gadgets.io.ContentType.JSON;
    params[gadgets.io.RequestParameters.AUTHORIZATION] = gadgets.io.AuthorizationType.OAUTH;
    params[gadgets.io.RequestParameters.OAUTH_SERVICE_NAME] = "EPLconext";
    params[gadgets.io.RequestParameters.METHOD] = gadgets.io.MethodType.GET;

    gadgets.io.makeRequest(url, function (response) {
        clog(response);
        if (response.oauthApprovalUrl) {
            // Create the popup handler. The onOpen function is called when the user
            // opens the popup window. The onClose function is called when the popup
            // window is closed.
            clog(response.oauthApprovalUrl);
            var popup = shindig.oauth.popup({
                destination: response.oauthApprovalUrl,
                windowOptions: null,
                onOpen: function() { showOneSection('waiting'); },
                onClose: function() { fetchData(); }
            });
            // Use the popup handler to attach onclick handlers to UI elements. The
            // createOpenerOnClick() function returns an onclick handler to open the
            // popup window. The createApprovedOnClick function returns an onclick
            // handler that will close the popup window and attempt to fetch the user's
            // data again.

            var personalize = document.getElementById('personalize');
            personalize.onclick = popup.createOpenerOnClick();
            var approvaldone = document.getElementById('approvaldone');
            approvaldone.onclick = popup.createApprovedOnClick();
            decommission_splash();
            showOneSection('approval');
        } else if (response.data) {
            var mainDom = document.getElementById('main');
            mainDom.innerHTML = "";
            showOneSection('main');
            clog("Response data: " + response.data);
            decommission_splash();
            showList(response);
            jQInit(); // install click handlers
            gadgets.window.adjustHeight();
        } else {
//            console.log('text/data:' + response.text + '-/-' + response.data);
//            // The response.oauthError and response.oauthErrorText values may help debug
//            // problems with your gadget.
//            var main = document.getElementById('main');
//            var err = document.createTextNode('OAuth error: ' +
//                response.oauthError + ': ' + response.oauthErrorText);
//            main.appendChild(err);
            decommission_splash();
            showOneSection('main');
        }
        // always do:
        gadgets.window.adjustHeight();
    }, params);
}

function makeBig(padname) {
    var canvas = new gadgets.views.View("canvas");
    var prefs = new gadgets.Prefs();
    var groupnameArg = get_current_group();
    prefs.set("groupnameParam", groupnameArg);;
    prefs.set("padparam", padname);
    clog('Maximizing for pad '+padname);
    clog('Using group ' + get_current_group());

    var params = {
        padparam : padname,
        groupnameParam: groupnameArg
    };

    gadgets.views.requestNavigateTo(canvas,params);
}

function decommission_splash() {
    $('#splash').css('display', 'none');
    $('#content').css('display', 'block');
}

function messagebox(message, description) {
    decommission_splash();
    $('#feed').hide();
    $('#messagebox').show();
    $('#mbox_title').text(message);
    $('#mbox_description').html(description);
}

// Invoke makeRequest() to fetch a token that authorizes access to a given pad
// OAuth has been setup by now because fetchData does this.
function authorizeCanvasPad(padid, nw) {
    var params = {};
    url = gadgCtx.epl_baseurl+'padmanager.php/padaccesstoken/'+escape(groupcontext) + '/' + escape(padid);

    clog('Accessing URL: ' + url);
    params[gadgets.io.RequestParameters.CONTENT_TYPE] = gadgets.io.ContentType.JSON;
    params[gadgets.io.RequestParameters.AUTHORIZATION] = gadgets.io.AuthorizationType.OAUTH;
    params[gadgets.io.RequestParameters.OAUTH_SERVICE_NAME] = "EPLconext";
    params[gadgets.io.RequestParameters.METHOD] = gadgets.io.MethodType.GET;
    gadgets.io.makeRequest(url, function (response) {
        if (response.oauthApprovalUrl) {
            alert('OAuth not yet setup; flow error?');
            return;
        }

        if (response.data) {
            var j = response.data.data;
            pat = j.padaccesstoken;

            // take to url:
            var url = gadgCtx.epl_baseurl+'main-canvas.php?pat='+pat;
            //window.open(url);
            nw.location = url;
//            $.get(url, function(page_result) {
//                w.document.write(page_result.page_content);
//            });

        } else {
//            console.log('text/data:' + response.text + '-/-' + response.data);
        }
    }, params);

}

function gadgetLoaded() {

    // execute everything after the user_id is set in context
    osapi.people.get({userId: '@owner'}).execute(function(result){
        if (!result.error) {
            osapi.groups.get().execute(function(d) {
                clog(d);
                user_id = result.id;
                jQInit();
                window.addEventListener("message", function(e){
                    if (e.data) {
                        currentGroup = e.data;
                        doWithGroupname(fetchData);
                        setInterval(function() {
                            clog("15 seconds up. Updating feed.");
                            doWithGroupname(fetchData);
                        }, 15000);
                    } else {
                        clog("No group.");
                        messagebox('No group selected.', 'Please select a group to work with this application.');
                    }

                }, false);
                top.postMessage("update",top.location.origin);
            });
        }

//        decommission_splash();
    });
}

// Call gadgetLoaded() when gadget loads.
gadgets.util.registerOnLoadHandler(gadgetLoaded);

]]>
</Content>
    <!-- ================================================================================================ -->
    <!-- would want: ${UserPrefs.groupContext} -->
<!--<Content type="html" view="canvas">-->
<!--    <![CDATA[-->
<!--    <div id="dEtherpadLite"></div>-->
<!--    <script src="https://etherpad-groups.identitylabs.org/eplconext/gadget/h.js"></script>-->
<!--<script type="text/javascript">-->
<!---->
<!--    function clog(message) {-->
<!--        console.log("(*) Etherpad-Groups: " + message);-->
<!--    }-->
<!---->
<!--    // duplicated in each view:-->
<!--    var gadgCtx = {-->
<!--        epl_baseurl: 'https://etherpad-groups.identitylabs.org/eplconext/',-->
<!--        portal_baseurl: top.location.origin-->
<!--    };-->
<!---->
<!--    // Invoke makeRequest() to fetch a token that authorizes access to a given pad-->
<!--    // OAuth has been setup by now because fetchData does this.-->
<!--    function authorizeCanvasPad(padid) {-->
<!--        var params = {};-->
<!--        url = gadgCtx.epl_baseurl+'padmanager.php/padaccesstoken/'+escape(groupcontext) + '/' + escape(padid);-->
<!---->
<!--        clog('Accessing URL: ' + url);-->
<!--        params[gadgets.io.RequestParameters.CONTENT_TYPE] = gadgets.io.ContentType.JSON;-->
<!--        params[gadgets.io.RequestParameters.AUTHORIZATION] = gadgets.io.AuthorizationType.OAUTH;-->
<!--        params[gadgets.io.RequestParameters.OAUTH_SERVICE_NAME] = "EPLconext";-->
<!--        params[gadgets.io.RequestParameters.METHOD] = gadgets.io.MethodType.GET;-->
<!--        gadgets.io.makeRequest(url, function (response) {-->
<!--            if (response.oauthApprovalUrl) {-->
<!--                alert('OAuth not yet setup; flow error?');-->
<!--                return;-->
<!--            }-->
<!---->
<!--            if (response.data) {-->
<!--                var j = response.data.data;-->
<!--                pat = j.padaccesstoken;-->
<!---->
<!--                // take to url:-->
<!--                var url = gadgCtx.epl_baseurl+'main-canvas.php?pat='+pat;-->
<!---->
<!--                var ifr = cozmanovaHelper.createElementWithAttributes('iframe', {-->
<!--                    'src':url,-->
<!--                    'frameborder':0, 'scrolling':'auto', 'width':'100%', 'height':'600px'-->
<!--                });-->
<!---->
<!--                document.getElementById('dEtherpadLite').appendChild(ifr);-->
<!---->
<!--                gadgets.window.adjustHeight();-->
<!---->
<!--            } else {-->
<!--//                console.log('text/data:' + response.text + '-/-' + response.data);-->
<!--            }-->
<!--        }, params);-->
<!---->
<!--    }-->
<!---->
<!--    var prefs;-->
<!--    var groupcontext;-->
<!--    var padname;-->
<!--    var currentGroup;-->
<!---->
<!--    function gadgetLoaded() {-->
<!--        clog('Canvas view executes gadgetLoaded()');-->
<!---->
<!--        var prefs = gadgets.views.getParams();-->
<!--        groupcontext = prefs['groupnameParam'];-->
<!--        padname = prefs['padparam'];-->
<!---->
<!--        authorizeCanvasPad(padname);-->
<!--    }-->
<!---->
<!--    clog('Canvas view executes global script.');-->
<!---->
<!--    gadgets.util.registerOnLoadHandler(gadgetLoaded);-->
<!---->
<!--</script>-->
<!---->
<!--]]>-->
<!--</Content>-->

</Module>
