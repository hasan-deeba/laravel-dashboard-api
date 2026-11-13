# Client Firebase push notifications

The current push slice contains a test push for an authenticated client and reusable notification classes/helpers. It does not include a dashboard campaign composer or production event triggers.

## Firebase setup

1. Create a project in the [Firebase Console](https://console.firebase.google.com/) and add the Android application ID and iOS bundle ID.
2. Enable Firebase Cloud Messaging. For iOS, configure APNs credentials in the Firebase project's iOS app settings.
3. Download `google-services.json` (Android) and `GoogleService-Info.plist` (iOS) for the mobile app. These app configuration files are not the server Admin SDK secret.
4. Create a Firebase Admin SDK service-account JSON key. Keep it outside Git and outside the public web root, then set the API server environment variable to its absolute path:

```env
FIREBASE_CREDENTIALS=/absolute/path/to/firebase-service-account.json
```

Never put the service-account JSON/private key in the mobile app, repository, or API request. The backend uses it to authenticate with FCM HTTP v1.
5. The mobile app integrates Firebase Messaging, requests notification permission, and obtains an FCM device token.

## Device-token lifecycle

`device_token` and `device_platform` are optional on login and OTP verification, but must be sent together. Registration does not accept/store a token. Example login fields:

```json
{
  "email": "client@example.com",
  "password": "password",
  "device_token": "<FCM-registration-token>",
  "device_platform": "ios"
}
```

Use the same fields with `POST /api/verify-otp` after a new account is verified. On logout, send the token to remove that device from the client's registered devices:

```http
POST /api/logout
Authorization: Bearer <client-token>
Content-Type: application/json

{"device_token":"<FCM-registration-token>"}
```

There are no separate register/unregister device endpoints. Login or OTP verification upserts the token; logout removes only the submitted token. The mobile app should provide a refreshed token on its next login/verification because a separate token-refresh API is intentionally not included in this test slice.

## Test push

```http
POST /api/notifications/test
Authorization: Bearer <client-token>
Content-Type: application/json

{"title":"Push test","body":"Your app can receive notifications."}
```

This sends to all registered devices for the authenticated client (limited to three requests per minute) and stores the notification in Laravel's `notifications` table. Missing devices return 409; delivery/configuration failure returns 503. Notification channel selection and payload/database formats live in the notification classes under `app/Notifications`; the helper only selects recipients and dispatches them.

## Future bilingual dashboard broadcasts

A Firebase topic is not suitable when each recipient must receive a different Arabic or English title/body: a topic send has one shared payload. For that feature, save each client's preferred language, then create one Laravel notification per client that chooses the matching title/body and send through `NotificationHelper::sendToMultiUser()`. The client model currently has no stored preferred-language field, so that choice needs to be added before implementing the campaign composer. A topic is only appropriate for identical, non-sensitive broadcasts to all subscribers.
