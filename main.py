import sqlite3
import json
from datetime import date

# Чтение json-файла с отчетом
with open('report.json', 'r') as file:
    report = json.load(file)

# Подключение к базе данных
conn = sqlite3.connect('database.sqlite')
cursor = conn.cursor()

# Получение актуальных тарифов на текущую дату
current_date = date.today()
query = "SELECT * FROM Tariff WHERE start_date <= :current_date AND end_date >= :current_date"
tariffs = cursor.execute(query, {'current_date': current_date}).fetchall()

# Подготовка запроса для расчета и сохранения расходов
query_insert = "INSERT INTO Expense (resale_object, sum) VALUES (?, ?)"

# Обработка каждого объекта из отчета
for item in report['result']['offers']:
    if item['status'] == 'Published':
        # Поиск соответствующего объекта в базе данных
        query = "SELECT ResaleObject.id, area, Tariff.price FROM main.ResaleObject JOIN Tariff ON start_date < DATE('now') and end_date > DATE('now') WHERE ResaleObject.guid=:external_id"
        resale_obj = cursor.execute(query, {'external_id': item['externalId']}).fetchone()

        # Расчет суммы расходов на рекламу
        total_expense = 0
        if resale_obj:
            obj_id, area, price = resale_obj
            expense_per_obj = price / len(report)  # Расчет суммы на каждый объект
            total_expense = expense_per_obj / len(tariffs)  # Расчет суммы на каждый объект с учетом тарифов

        # Сохранение результатов в таблицу Expense
        cursor.execute(query_insert, (obj_id, total_expense))
        conn.commit()

# Выполнение выборок из таблицы Expense
# 1. все опубликованные квартиры, площадью от 25 кв. м до 70 кв. м включительно, у которых расходы не менее 1000 руб.
query = """
    SELECT ResaleObject.address
    FROM ResaleObject
    JOIN Expense ON ResaleObject.id = Expense.resale_object
    WHERE ResaleObject.area >= 25 AND ResaleObject.area <= 70 AND Expense.sum >= 1000
"""
result_1 = cursor.execute(query).fetchall()

# 2. все архивные объекты (статус close) с нулевым или отсутствующим расходом
query = """
    SELECT ResaleObject.address
    FROM ResaleObject
    LEFT JOIN Expense ON ResaleObject.id = Expense.resale_object
    WHERE ResaleObject.status = 'close' AND (Expense.sum IS NULL OR Expense.sum = 0)
"""
result_2 = cursor.execute(query).fetchall()

# 3. количество объектов, расходы на которые превышают среднее значение расхода на объект
query = """
    SELECT COUNT(*) FROM Expense WHERE sum > (SELECT AVG(sum) FROM Expense)
"""
result_3 = cursor.execute(query).fetchone()

# Закрытие соединения с базой данных
conn.close()

# Вывод результатов на экран
print("Результат выборки 1:")
for item in result_1:
    print(item[0])

print("Результат выборки 2:")
for item in result_2:
    print(item[0])

print("Результат выборки 3:")
print(result_3[0])