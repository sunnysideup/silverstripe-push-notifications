const MyOneSignalCommsBackToWebsite = {
  currentUserId: '',

  init: function (OneSignal) {
    // Check if the user is already subscribed and get their user ID
    OneSignal.getUserId().then(function (userId) {
      if (userId) {
        MyOneSignalCommsBackToWebsite.currentUserId = userId
        console.log('User ID available:', userId)
        // Handle the user ID (e.g., send it to your server)
      } else {
        console.log('User ID not yet available.')
        // Optionally, you can handle cases where the user ID is not yet assigned
      }
    })

    // set up a listener for the subscription change event
    OneSignal.push(function () {
      OneSignal.on('subscriptionChange', function (isSubscribed) {
        if (isSubscribed) {
          // User subscribed
          OneSignal.getUserId().then(function (userId) {
            MyOneSignalCommsBackToWebsite.currentUserId = userId
            MyOneSignalCommsBackToWebsite.subscribeOrSubscribeToOneSignal(true)
            console.log(
              'User subscribed with ID:',
              MyOneSignalCommsBackToWebsite.currentUserId
            )
            // Store the userId for later use if needed
          })
        } else {
          // User unsubscribed
          if (MyOneSignalCommsBackToWebsite.currentUserId) {
            console.log(
              'User with ID:',
              MyOneSignalCommsBackToWebsite.currentUserId,
              'has unsubscribed.'
            )
            MyOneSignalCommsBackToWebsite.subscribeOrSubscribeToOneSignal(false)

            // Handle the unsubscription, e.g., notify your server
          } else {
            console.log('User unsubscribed but no ID available.')
          }
        }
      })
    })
  },

  subscribeOrSubscribeToOneSignal: function (isSubscribe) {
    if (!window.push_notification_url) {
      alert(
        'ERROR: push_notification_url is not defined. Please try again at a later date.'
      )
      return
    }
    if (!MyOneSignalCommsBackToWebsite.currentUserId) {
      alert('ERROR: userId is not defined. Please try again at a later date.')
      return
    }
    // Replace 'xxx' with your actual base URL
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
        userId: MyOneSignalCommsBackToWebsite.currentUserId
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
