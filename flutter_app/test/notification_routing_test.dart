import 'package:flutter_test/flutter_test.dart';
import 'package:top_organic_app/core/utils/notification_router.dart';

void main() {
  test('mail alerts open internal inbox from entity or web URL', () {
    expect(
      resolveNotificationRoute(
        entityType: 'internal_mail',
        actionType: 'new_message',
        link: '/mail/123',
      ),
      '/mail',
    );
    expect(
      resolveNotificationRoute(link: 'https://www.toporganic.rest/mail/123'),
      '/mail',
    );
    expect(resolveNotificationRoute(link: '/mail'), '/mail');
    expect(resolveNotificationRoute(link: '/unrelated'), '/notifications');
    expect(
      resolveNotificationRoute(
        entityType: 'request',
        actionType: 'pending_approval',
      ),
      '/approvals',
    );
    expect(
      resolveNotificationRoute(entityType: 'request', actionType: 'approved'),
      '/archive',
    );
  });
  test(
    'foreground alert and tap reject another account or a logged-out session',
    () {
      expect(
        notificationBelongsToUser({'recipient_user_id': '20'}, 20),
        isTrue,
      );
      expect(
        notificationBelongsToUser({'recipient_user_id': 20}, '20'),
        isTrue,
      );
      expect(
        notificationBelongsToUser({'recipient_user_id': '10'}, 20),
        isFalse,
      );
      expect(
        notificationBelongsToUser({'recipient_user_id': '20'}, null),
        isFalse,
      );
      expect(notificationBelongsToUser({}, null), isFalse);
      expect(notificationBelongsToUser({}, 20), isTrue);
    },
  );
}
