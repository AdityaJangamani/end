"""
train_from_db.py
================
Trains all four AI models using REAL employee data from the hr_ai_system
MySQL database, instead of synthetic data.

Requirements:
    pip install pandas scikit-learn joblib mysql-connector-python

Usage:
    python train_from_db.py

Models saved to: ../models/
    salary_model.pkl
    attrition_model.pkl
    promotion_model.pkl
    category_model.pkl
"""

import os
import sys
import joblib
import pandas as pd
import numpy as np
import mysql.connector
from sklearn.linear_model import LinearRegression, LogisticRegression
from sklearn.ensemble import RandomForestClassifier
from sklearn.tree import DecisionTreeClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import (
    mean_absolute_error, r2_score,
    accuracy_score, classification_report
)

# ── Database connection ────────────────────────────────────────────────────────
DB_CONFIG = {
    "host":     "localhost",
    "user":     "root",
    "password": "",          # change if your MySQL has a password
    "database": "hr_ai_system",
}

# ── Output directory ───────────────────────────────────────────────────────────
MODELS_DIR = os.path.join(os.path.dirname(__file__), "../models")
os.makedirs(MODELS_DIR, exist_ok=True)

# ── Feature columns (must match app.py's create_feature_df) ───────────────────
FEATURE_COLS = [
    "Age",
    "YearsAtCompany",
    "BaseSalary",
    "JobSatisfaction",
    "PerformanceRating",
    "ProjectsCompleted",
    "HoursWorkedPerWeek",
]


# ── 1. Load data from the database ────────────────────────────────────────────
def load_data() -> pd.DataFrame:
    """
    Joins employees + performance + salary + attendance into one flat DataFrame.
    Each employee row uses their LATEST performance evaluation and salary record.
    """
    print("Connecting to database...")
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
    except mysql.connector.Error as e:
        print(f"ERROR: Could not connect to MySQL.\n  {e}")
        print("\nMake sure:")
        print("  1. MySQL / XAMPP is running")
        print("  2. DB_CONFIG credentials above are correct")
        sys.exit(1)

    query = """
        SELECT
            e.id                        AS employee_db_id,
            e.age                       AS Age,
            e.years_at_company          AS YearsAtCompany,
            e.job_satisfaction          AS JobSatisfaction,
            CASE
                WHEN e.has_left IN ('1', 'Yes', 'yes', 'YES', '1') THEN 1
                ELSE 0
            END                         AS HasLeft,

            -- Latest salary record
            s.base_salary               AS BaseSalary,
            s.net_salary                AS NetSalary,

            -- Latest performance record
            p.productivity_score        AS ProductivityScore,
            p.manager_rating            AS ManagerRating,
            p.projects_completed        AS ProjectsCompleted,
            p.hours_worked_per_week     AS HoursWorkedPerWeek,

            -- Attendance (avg % presence across all recorded months)
            COALESCE(att.avg_presence, 100) AS AvgPresencePct

        FROM employees e

        -- Latest salary: pick the most recent record per employee
        LEFT JOIN (
            SELECT s1.*
            FROM salary s1
            INNER JOIN (
                SELECT employee_id, MAX(id) AS max_id
                FROM salary
                GROUP BY employee_id
            ) s2 ON s1.employee_id = s2.employee_id AND s1.id = s2.max_id
        ) s ON s.employee_id = e.id

        -- Latest performance record
        LEFT JOIN (
            SELECT p1.*
            FROM performance p1
            INNER JOIN (
                SELECT employee_id, MAX(evaluation_date) AS max_date
                FROM performance
                GROUP BY employee_id
            ) p2 ON p1.employee_id = p2.employee_id
               AND p1.evaluation_date = p2.max_date
        ) p ON p.employee_id = e.id

        -- Average attendance (optional)
        LEFT JOIN (
            SELECT employee_id,
                   AVG(days_present / NULLIF(total_days, 0) * 100) AS avg_presence
            FROM attendance
            GROUP BY employee_id
        ) att ON att.employee_id = e.id

        WHERE e.status = 'Active'
           OR e.has_left IN ('1', 'Yes', 'yes', 'YES')
           OR e.has_left = '1'
    """

    df = pd.read_sql(query, conn)
    conn.close()
    print(f"  Loaded {len(df)} employee records from the database.")
    return df


# ── 2. Derive targets from real data ──────────────────────────────────────────
def build_targets(df: pd.DataFrame) -> pd.DataFrame:
    """
    Creates four target columns from real data:

    TargetSalary         — net_salary (what the employee actually earns)
    Attrition            — 1 if the employee has left, else 0
    Promotion            — 1 if manager_rating >= 4 AND projects_completed >= 10
                           AND years_at_company >= 2  (proxy for promotion-ready)
    Category             — 0 Underperformer / 1 Steady / 2 High Potential
                           derived from performance + projects_completed
    PerformanceRating    — rescaled from manager_rating (1-5 scale, rounded)
    """

    # PerformanceRating: manager rates 0.0–5.0 → round to nearest int, clip 1–5
    df["PerformanceRating"] = df["ManagerRating"].round().clip(1, 5).astype(int)

    # Target: salary — use a derived formula to avoid data leakage
    # (BaseSalary is already a feature, so we can't use NetSalary directly)
    df["TargetSalary"] = (
        df["BaseSalary"]
        + df["YearsAtCompany"] * 1000
        + df["PerformanceRating"] * 2000
        + df["ProjectsCompleted"] * 500
    )

    # Target: attrition
    # If all employees are Active (no one has left), generate a realistic proxy
    # based on: low job satisfaction, high work hours, low salary → likely to leave
    raw_attrition = df["HasLeft"].astype(int)
    if raw_attrition.nunique() < 2:
        print("  [!] All employees are Active -- generating proxy attrition labels from risk factors.")
        import numpy as np
        np.random.seed(42)
        # Risk score: higher = more likely to leave
        risk = (
            (5 - df["JobSatisfaction"].clip(1, 5)) * 0.4
            + (df["HoursWorkedPerWeek"] - 40).clip(0) * 0.05
            - (df["BaseSalary"] - df["BaseSalary"].mean()) / df["BaseSalary"].std() * 0.1
        )
        # Top ~25% risk employees are marked as would-leave
        threshold = risk.quantile(0.75)
        df["Attrition"] = (risk >= threshold).astype(int)
        print(f"  Proxy attrition: {df['Attrition'].sum()} at-risk / {len(df)} total employees")
    else:
        df["Attrition"] = raw_attrition

    # Target: promotion proxy
    df["Promotion"] = (
        (df["PerformanceRating"] >= 4) &
        (df["ProjectsCompleted"] >= 10) &
        (df["YearsAtCompany"] >= 2)
    ).astype(int)

    # Target: intelligent category
    def categorise(row):
        if row["PerformanceRating"] >= 4 and row["ProjectsCompleted"] >= 15:
            return 2   # High Potential
        if row["PerformanceRating"] <= 2 or row["ProjectsCompleted"] <= 5:
            return 0   # Underperformer
        return 1       # Steady

    df["Category"] = df.apply(categorise, axis=1)

    return df


# ── 3. Train & evaluate helpers ───────────────────────────────────────────────
def split(df, target):
    X = df[FEATURE_COLS]
    y = df[target]
    return train_test_split(X, y, test_size=0.2, random_state=42)


def train_salary(df):
    print("\n[1/4] Salary Prediction - Linear Regression")
    X_tr, X_te, y_tr, y_te = split(df, "TargetSalary")
    model = LinearRegression()
    model.fit(X_tr, y_tr)
    preds = model.predict(X_te)
    mae = float(mean_absolute_error(y_te, preds))
    r2 = float(r2_score(y_te, preds))
    print(f"  MAE : Rs.{mae:,.0f}")
    print(f"  R2  : {r2:.3f}")
    path = os.path.join(MODELS_DIR, "salary_model.pkl")
    joblib.dump(model, path)
    print(f"  Saved -> {path}")
    return {"mae": mae, "r2": r2}


def train_attrition(df):
    print("\n[2/4] Attrition Prediction - Logistic Regression")
    X_tr, X_te, y_tr, y_te = split(df, "Attrition")
    model = LogisticRegression(max_iter=1000, random_state=42)
    model.fit(X_tr, y_tr)
    preds = model.predict(X_te)
    acc = float(accuracy_score(y_te, preds))
    print(f"  Accuracy : {acc:.2%}")
    print(classification_report(y_te, preds,
          target_names=["Stayed", "Left"], zero_division=0))
    path = os.path.join(MODELS_DIR, "attrition_model.pkl")
    joblib.dump(model, path)
    print(f"  Saved -> {path}")
    return {"accuracy": acc}


def train_promotion(df):
    print("\n[3/4] Promotion Prediction - Random Forest")
    X_tr, X_te, y_tr, y_te = split(df, "Promotion")
    model = RandomForestClassifier(n_estimators=100, random_state=42)
    model.fit(X_tr, y_tr)
    preds = model.predict(X_te)
    acc = float(accuracy_score(y_te, preds))
    print(f"  Accuracy : {acc:.2%}")
    print(classification_report(y_te, preds,
          target_names=["Not ready", "Ready"], zero_division=0))
    path = os.path.join(MODELS_DIR, "promotion_model.pkl")
    joblib.dump(model, path)
    print(f"  Saved -> {path}")
    return {"accuracy": acc}


def train_category(df):
    print("\n[4/4] Employee Category - Decision Tree")
    X_tr, X_te, y_tr, y_te = split(df, "Category")
    model = DecisionTreeClassifier(max_depth=6, random_state=42)
    model.fit(X_tr, y_tr)
    preds = model.predict(X_te)
    acc = float(accuracy_score(y_te, preds))
    print(f"  Accuracy : {acc:.2%}")
    label_map = {0: "Underperformer", 1: "Steady", 2: "High Potential"}
    present_labels = sorted(set(y_te) | set(preds))
    present_names  = [label_map[l] for l in present_labels]
    print(classification_report(y_te, preds,
          labels=present_labels, target_names=present_names,
          zero_division=0))
    path = os.path.join(MODELS_DIR, "category_model.pkl")
    joblib.dump(model, path)
    print(f"  Saved -> {path}")
    return {"accuracy": acc}


# ── 4. Log the training run back to the database ──────────────────────────────
def log_training(total: int, used: int, notes: str):
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cur = conn.cursor()
        cur.execute("""
            INSERT INTO training_data_log
                (total_records, records_used, data_version, notes)
            VALUES (%s, %s, %s, %s)
        """, (total, used, "db_v1", notes))
        conn.commit()
        conn.close()
        print("\n  Training run logged to training_data_log table.")
    except Exception as e:
        print(f"\n  Warning: could not write to training_data_log - {e}")


# ── 5. Main ───────────────────────────────────────────────────────────────────
def main():
    print("=" * 60)
    print("  AI System — Training from real database data")
    print("=" * 60)

    df_raw = load_data()

    if len(df_raw) < 10:
        print(
            f"\nERROR: Only {len(df_raw)} records found after joining tables.\n"
            "  Make sure employees have matching salary AND performance records.\n"
            "  Add data through the system first, then retrain."
        )
        sys.exit(1)

    df = build_targets(df_raw)

    # Drop rows with any NaN in feature or target columns
    needed = FEATURE_COLS + ["TargetSalary", "Attrition", "Promotion", "Category"]
    before = len(df)
    df = df.dropna(subset=needed)
    after = len(df)
    if before != after:
        print(f"  Dropped {before - after} rows with missing values. Using {after} records.")

    m_salary = train_salary(df)
    m_attrition = train_attrition(df)
    m_promotion = train_promotion(df)
    m_category = train_category(df)

    import json
    notes_json = json.dumps({
        "salary_mae": m_salary["mae"],
        "salary_r2": m_salary["r2"],
        "attrition_accuracy": m_attrition["accuracy"],
        "promotion_accuracy": m_promotion["accuracy"],
        "category_accuracy": m_category["accuracy"]
    })

    log_training(total=before, used=after, notes=notes_json)

    print("\n" + "=" * 60)
    print("  All 4 models trained and saved to ../models/")
    print("  Restart app.py (Flask server) to load the new models.")
    print("=" * 60)


if __name__ == "__main__":
    main()