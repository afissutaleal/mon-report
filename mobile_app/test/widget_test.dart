import 'package:flutter_test/flutter_test.dart';
import 'package:monreport_mobile/main.dart';

void main() {
  testWidgets('MonReport app smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const MonReportApp());
    expect(find.byType(MonReportApp), findsOneWidget);
  });
}
