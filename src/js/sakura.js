iFrameResize({
    log                     : false,                  // Disable console logging
    // maxHeight: 400,
		checkOrigin: ["https://www.sakura.eco"],
    onResized         : function(messageData){ // Callback fn when resize is received
	// console.log(
	//     '<b>Frame ID:</b> '    + messageData.iframe.id +
	//     ' <b>Height:</b> '     + messageData.height +
	//     ' <b>Width:</b> '      + messageData.width +
	//     ' <b>Event type:</b> ' + messageData.type
	// );
    },
    onMessage         : function(messageData){ // Callback fn when message is received
	// console.log(
	//     '<b>Frame ID:</b> '    + messageData.iframe.id +
	//     ' <b>Message:</b> '    + messageData.message
	// );
	// alert(messageData.message);
    },
    onClosed         : function(id){ // Callback fn when iFrame is closed
	// console.log(
	//     '<b>IFrame (</b>'    + id +
	//     '<b>) removed from page.</b>'
	// );
    }
}, '.sakura');
