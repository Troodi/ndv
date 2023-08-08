<?
// Соединение с SQLite БД
$db = new SQLite3('database.sqlite');

// Чтение файла-отчета
$report = file_get_contents('report.json');
$reportData = json_decode($report, true);

function processReport($reportData, $db) {
  $expenses = [];

  foreach ($reportData as $item) {
    if ($item['status'] == 'Published') {
      // Получаем информацию о объекте вторичной недвижимости из БД
      $stmt = $db->prepare('SELECT * FROM ResaleObject WHERE guid = :guid');
      $stmt->bindValue(':guid', $item['externalId'], SQLITE3_TEXT);
      $result = $stmt->execute();
      $resaleObject = $result->fetchArray(SQLITE3_ASSOC);

      // Получаем информацию о тарифе
      $startDate = date('Y-m-d');
      $endDate = date('Y-m-d');
      $stmt = $db->prepare('SELECT * FROM Tariff WHERE startdate <= :startDate AND enddate >= :endDate');
      $stmt->bindValue(':startDate', $startDate, SQLITE3_TEXT);
      $stmt->bindValue(':endDate', $endDate, SQLITE3_TEXT);
      $result = $stmt->execute();
      $tariff = $result->fetchArray(SQLITE3_ASSOC);

      // Вычисляем расходы на рекламу объекта
      $daysInTariff = (strtotime($tariff['enddate']) - strtotime($tariff['startdate'])) / (60 * 60 * 24);
      $totalExpenses = $tariff['price'] / ($daysInTariff * count($reportData));
      $expense = [
        'resale_object' => $resaleObject['id'],
        'sum' => $totalExpenses
      ];

      // Добавляем расходы в массив
      $expenses[] = $expense;
    }
  }

  return $expenses;
}

// Обработка файла-отчета
$expenses = processReport($reportData, $db);

// Запрос на опубликованные квартиры с площадью от 25 кв. м до 70 кв. м и расходами не менее 1000 руб.
$stmt = $db->prepare('SELECT * FROM ResaleObject
                      JOIN Expense ON ResaleObject.id = Expense.resale_object
                      WHERE ResaleObject.status = "active"
                      AND ResaleObject.area BETWEEN 25 AND 70
                      AND Expense.sum >= 1000');
$result = $stmt->execute();
$publishedApartments = $result->fetchArray(SQLITE3_ASSOC);

// Запрос на архивные объекты с нулевым или отсутствующим расходом
$stmt = $db->prepare('SELECT * FROM ResaleObject
                      LEFT JOIN Expense ON ResaleObject.id = Expense.resale_object
                      WHERE ResaleObject.status = "close"
                      AND (Expense.sum IS NULL OR Expense.sum = 0)');
$result = $stmt->execute();
$archivedObjects = $result->fetchArray(SQLITE3_ASSOC);

// Запрос на количество объектов, расходы на которые превышают среднее значение расхода на объект
$stmt = $db->prepare('SELECT COUNT(*) as count FROM ResaleObject
                      JOIN Expense ON ResaleObject.id = Expense.resale_object
                      WHERE Expense.sum > (
                        SELECT AVG(sum) FROM Expense
                      )');
$result = $stmt->execute();
$count = $result->fetchArray(SQLITE3_ASSOC);
$count = $count['count'];
