import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../core/config.dart';
import '../core/theme.dart';
import '../core/locale/locale_provider.dart';
import '../core/locale/app_strings.dart';
import '../features/auth/auth_provider.dart';
import '../features/notifications/notifications_provider.dart';
import 'nav_items.dart';

/// هيكل تنقل متجاوب: NavigationRail للشاشات الكبيرة (تابلت/سطح مكتب)
/// وDrawer + BottomNavigationBar (لأهم 5 عناصر) للهاتف
/// يفلتر عناصر التنقل حسب صلاحيات المستخدم الحالي (عنصر بلا شرط صلاحية يظهر دائماً)
List<NavItem> _visibleNavItems(AuthProvider auth) {
  return kNavItems.where((e) => navItemVisible(e, auth)).toList();
}

List<NavItem> _visibleBottomNavItems(AuthProvider auth) {
  return kBottomNavItems.where((e) => navItemVisible(e, auth)).toList();
}

/// نص الوظيفة والقسم يظهر تحت اسم المستخدم في رأس الملف الشخصي
String _subtitleText(AuthProvider auth) {
  final jobTitle = auth.user?['job_title']?.toString().trim() ?? '';
  final department = auth.user?['department']?.toString().trim() ?? '';
  return [jobTitle, department].where((s) => s.isNotEmpty).join(' • ');
}

class AppShell extends StatelessWidget {
  const AppShell({super.key, required this.child, required this.location});

  final Widget child;
  final String location;

  int _selectedIndex(List<NavItem> items) {
    final idx = items.indexWhere((e) => location.startsWith(e.path));
    return idx == -1 ? 0 : idx;
  }

  void _onSelect(BuildContext context, List<NavItem> items, int index) {
    context.go(items[index].path);
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final auth = context.watch<AuthProvider>();
    final allItems = _visibleNavItems(auth);
    final bottomItems = _visibleBottomNavItems(auth);
    final isWide =
        MediaQuery.sizeOf(context).width >= AppConfig.tabletBreakpoint;
    final selectedIndex = _selectedIndex(allItems);
    final bottomSelectedIndex = _selectedIndex(bottomItems);
    final title = allItems.isEmpty ? '' : t(allItems[selectedIndex].label);

    if (isWide) {
      return Scaffold(
        body: Row(
          children: [
            LayoutBuilder(
              builder: (context, constraints) {
                return SingleChildScrollView(
                  child: ConstrainedBox(
                    constraints: BoxConstraints(
                      minHeight: constraints.maxHeight,
                    ),
                    child: IntrinsicHeight(
                      child: NavigationRail(
                        selectedIndex: selectedIndex,
                        onDestinationSelected: (i) =>
                            _onSelect(context, allItems, i),
                        labelType: NavigationRailLabelType.all,
                        leading: const Padding(
                          padding: EdgeInsets.symmetric(vertical: 12),
                          child: Icon(Icons.eco, color: Colors.white, size: 32),
                        ),
                        destinations: allItems
                            .map(
                              (e) => NavigationRailDestination(
                                icon: Icon(e.icon),
                                label: Text(t(e.label)),
                              ),
                            )
                            .toList(),
                      ),
                    ),
                  ),
                );
              },
            ),
            const VerticalDivider(width: 1),
            Expanded(
              child: Column(
                children: [
                  _TopBar(title: title),
                  Expanded(child: child),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        actions: const [_NotificationBell(), SizedBox(width: 8)],
      ),
      drawer: _AppDrawer(
        items: allItems,
        selectedIndex: selectedIndex,
        onSelect: (i) {
          Navigator.pop(context);
          _onSelect(context, allItems, i);
        },
      ),
      body: child,
      bottomNavigationBar: bottomItems.isEmpty
          ? null
          : NavigationBar(
              selectedIndex: bottomSelectedIndex.clamp(
                0,
                bottomItems.length - 1,
              ),
              onDestinationSelected: (i) => _onSelect(context, bottomItems, i),
              destinations: bottomItems
                  .map(
                    (e) => NavigationDestination(
                      icon: Icon(e.icon),
                      label: t(e.label),
                    ),
                  )
                  .toList(),
            ),
    );
  }
}

class _TopBar extends StatelessWidget {
  const _TopBar({required this.title});
  final String title;

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(bottom: BorderSide(color: Color(0xFFE5E7EB))),
      ),
      child: Row(
        children: [
          Text(
            title,
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const Spacer(),
          const _NotificationBell(),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                auth.user?['name']?.toString() ?? '',
                style: const TextStyle(color: Colors.grey),
              ),
              if (_subtitleText(auth).isNotEmpty)
                Text(
                  _subtitleText(auth),
                  style: const TextStyle(color: Colors.grey, fontSize: 11),
                ),
            ],
          ),
          const SizedBox(width: 12),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => auth.logout(),
          ),
        ],
      ),
    );
  }
}

/// أيقونة جرس الإشعارات مع عداد غير المقروء — تظهر في كل واجهات التطبيق
class _NotificationBell extends StatelessWidget {
  const _NotificationBell();

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    final unread = context.watch<NotificationsProvider>().unreadCount;
    return IconButton(
      tooltip: AppStrings.t('nav_notifications', lang),
      icon: Badge(
        isLabelVisible: unread > 0,
        label: Text(unread > 99 ? '99+' : '$unread'),
        child: const Icon(Icons.notifications_outlined),
      ),
      onPressed: () => context.push('/notifications'),
    );
  }
}

class _AppDrawer extends StatelessWidget {
  const _AppDrawer({
    required this.items,
    required this.selectedIndex,
    required this.onSelect,
  });
  final List<NavItem> items;
  final int selectedIndex;
  final void Function(int) onSelect;

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final auth = context.watch<AuthProvider>();
    return Drawer(
      backgroundColor: AppTheme.sidebar,
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(20),
              child: Row(
                children: [
                  const Icon(Icons.eco, color: Colors.white, size: 30),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          auth.user?['name']?.toString() ?? AppConfig.appName,
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                          ),
                        ),
                        if (_subtitleText(auth).isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(top: 2),
                            child: Text(
                              _subtitleText(auth),
                              style: const TextStyle(
                                color: Colors.white70,
                                fontSize: 12,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const Divider(color: Colors.white24),
            Expanded(
              child: ListView(
                children: List.generate(items.length, (i) {
                  final item = items[i];
                  final selected = i == selectedIndex;
                  return ListTile(
                    leading: Icon(item.icon, color: Colors.white),
                    title: Text(
                      t(item.label),
                      style: const TextStyle(color: Colors.white),
                    ),
                    selected: selected,
                    selectedTileColor: AppTheme.sidebarHover,
                    onTap: () => onSelect(i),
                  );
                }),
              ),
            ),
            ListTile(
              leading: const Icon(Icons.logout, color: Colors.white),
              title: Text(
                t('logout'),
                style: const TextStyle(color: Colors.white),
              ),
              onTap: () => auth.logout(),
            ),
          ],
        ),
      ),
    );
  }
}
