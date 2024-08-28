window.MyOneSignalCommsBackToWebsite = {
  onesignalId: '',
  token: '',

  init: function (OneSignal) {
    console.debug(OneSignal)
    MyOneSignalCommsBackToWebsite.token
    OneSignal.User.PushSubscription.addEventListener(
      'change',
      MyOneSignalCommsBackToWebsite.pushSubscriptionChangeListener
    )
  },

  pushSubscriptionChangeListener: function (event) {
    console.debug('event', event)
    MyOneSignalCommsBackToWebsite.onesignalId = event.current.id
    const isSubscribed = event.current.optedIn
    MyOneSignalCommsBackToWebsite.token = event.current.token
    console.debug('isSubscribed', isSubscribed)
    console.debug('token', MyOneSignalCommsBackToWebsite.token)
    console.debug('oneSignalID', MyOneSignalCommsBackToWebsite.onesignalId)
    if (isSubscribed) {
      console.debug(
        'User to subscribe with ID:',
        MyOneSignalCommsBackToWebsite.onesignalId
      )
      MyOneSignalCommsBackToWebsite.subscribeOrSubscribeToOneSignal(true)
      console.debug(
        'User subscribed with ID:',
        MyOneSignalCommsBackToWebsite.onesignalId
      )
      // Store the userId for later use if needed
    } else {
      // User unsubscribed
      if (MyOneSignalCommsBackToWebsite.onesignalId) {
        console.debug(
          'User with ID:',
          MyOneSignalCommsBackToWebsite.onesignalId,
          'to unsubscribe.'
        )
        MyOneSignalCommsBackToWebsite.subscribeOrSubscribeToOneSignal(false)
        console.debug(
          'User with ID:',
          MyOneSignalCommsBackToWebsite.onesignalId,
          'has unsubscribed.'
        )

        // Handle the unsubscription, e.g., notify your server
      } else {
        console.log('ERROR: User unsubscribed but no ID available.')
      }
    }
  },

  subscribeOrSubscribeToOneSignal: function (isSubscribe) {
    if (!window.push_notification_url) {
      console.log(
        'ERROR: push_notification_url is not defined. Please try again at a later date.'
      )
      return
    }
    if (!MyOneSignalCommsBackToWebsite.onesignalId) {
      console.log(
        'ERROR: userId is not defined. Please try again at a later date.'
      )
      return
    }
    let method = 'subscribeonesignal'
    if (!isSubscribe) {
      method = 'un' + method
    }
    const url = window.push_notification_url + `/${method}`
    console.log(url)
    const urlEncodedData = new URLSearchParams({
      userId: MyOneSignalCommsBackToWebsite.onesignalId,
      token: MyOneSignalCommsBackToWebsite.token
    })

    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: urlEncodedData.toString()
    })
      .then(response => {
        if (response.ok) {
          console.debug(
            'User ID successfully ' +
              (isSubscribe ? 'subscribed' : 'unsubscribed') +
              '.'
          )
        } else {
          console.log(
            'ERROR: Failed to post user ID, error 2. Please try again at a later date.'
          )
        }
      })
      .catch(error => {
        console.log(
          'ERROR: Failed to post user ID, error 1. Please try again at a later date.'
        )
      })
  }
}
