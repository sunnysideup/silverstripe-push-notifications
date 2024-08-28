window.MyOneSignalCommsBackToWebsite = {
  onesignalId: '',

  init: function (OneSignal) {
    console.log(OneSignal)
    // set up a listener for the subscription change event
    OneSignal.User.addEventListener('subscriptionChange', function (event) {
      console.log('subscriptionChange', event)
      console.log(OneSignal.User)
      MyOneSignalCommsBackToWebsite.onesignalId = OneSignal.User.onesignalId
      if (isSubscribed) {
        MyOneSignalCommsBackToWebsite.subscribeOrSubscribeToOneSignal(true)
        console.log(
          'User subscribed with ID:',
          MyOneSignalCommsBackToWebsite.onesignalId
        )
        // Store the userId for later use if needed
      } else {
        // User unsubscribed
        if (MyOneSignalCommsBackToWebsite.onesignalId) {
          console.log(
            'User with ID:',
            MyOneSignalCommsBackToWebsite.onesignalId,
            'has unsubscribed.'
          )
          MyOneSignalCommsBackToWebsite.subscribeOrSubscribeToOneSignal(false)

          // Handle the unsubscription, e.g., notify your server
        } else {
          console.log('User unsubscribed but no ID available.')
        }
      }
    })
  },

  subscribeOrSubscribeToOneSignal: function (isSubscribe) {
    if (!window.push_notification_url) {
      alert(
        'ERROR: push_notification_url is not defined. Please try again at a later date.'
      )
      return
    }
    if (!MyOneSignalCommsBackToWebsite.onesignalId) {
      alert('ERROR: userId is not defined. Please try again at a later date.')
      return
    }
    let method = 'subscribeonesignal'
    if (!isSubscribe) {
      method = 'un' + method
    }
    const url = window.push_notification_url + `/${method}`

    // Send a POST request to your server
    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        userId: MyOneSignalCommsBackToWebsite.onesignalId
      })
    })
      .then(response => {
        if (response.ok) {
          console.log(
            'User ID successfully ' +
              (isSubscribe ? 'subscribed' : 'unsubscribed') +
              '.'
          )
        } else {
          alert(
            'ERROR: Failed to post user ID, error 2. Please try again at a later date.'
          )
        }
      })
      .catch(error => {
        alert(
          'ERROR: Failed to post user ID, error 1. Please try again at a later date.'
        )
      })
  }
}
