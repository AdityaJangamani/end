from flask import Flask, request, jsonify
from flask_cors import CORS
import pandas as pd
import joblib
import os

app = Flask(__name__)
CORS(app)

@app.route('/')
def home():
    return jsonify({
        "status": "online",
        "message": "AI System Prediction API is running",
        "endpoints": ["/predict_salary", "/predict_attrition", "/predict_promotion",
                       "/predict_bonus", "/predict_deduction", "/predict_intelligent", "/analyze_productivity"]
    })

# Load Models
models_dir = os.path.join(os.path.dirname(__file__), '../models')

try:
    model_salary = joblib.load(os.path.join(models_dir, 'salary_model.pkl'))
    model_attrition = joblib.load(os.path.join(models_dir, 'attrition_model.pkl'))
    model_promotion = joblib.load(os.path.join(models_dir, 'promotion_model.pkl'))
    model_category = joblib.load(os.path.join(models_dir, 'category_model.pkl'))
except Exception as e:
    print(f"Error loading models: {e}")

def create_feature_df(data):
    # Features: Age, YearsAtCompany, BaseSalary, JobSatisfaction, PerformanceRating, ProjectsCompleted, HoursWorkedPerWeek
    features = {
        'Age': [float(data.get('Age', 30))],
        'YearsAtCompany': [float(data.get('YearsAtCompany', 2))],
        'BaseSalary': [float(data.get('BaseSalary', 50000))],
        'JobSatisfaction': [float(data.get('JobSatisfaction', 3))],
        'PerformanceRating': [float(data.get('PerformanceRating', 3))],
        'ProjectsCompleted': [float(data.get('ProjectsCompleted', 5))],
        'HoursWorkedPerWeek': [float(data.get('HoursWorkedPerWeek', 40))]
    }
    return pd.DataFrame(features)

@app.route('/predict_salary', methods=['POST'])
def predict_salary():
    data = request.json
    df = create_feature_df(data)
    pred = model_salary.predict(df)[0]
    return jsonify({'predicted_salary': round(pred, 2)})

@app.route('/predict_attrition', methods=['POST'])
def predict_attrition():
    data = request.json
    df = create_feature_df(data)
    # Predict probabilities to get a percentage
    prob = model_attrition.predict_proba(df)[0][1] * 100
    return jsonify({'attrition_risk': round(prob, 2)})

@app.route('/predict_promotion', methods=['POST'])
def predict_promotion():
    data = request.json
    df = create_feature_df(data)
    prob = model_promotion.predict_proba(df)[0][1] * 100
    return jsonify({'promotion_probability': round(prob, 2)})

@app.route('/predict_intelligent', methods=['POST'])
def predict_intelligent():
    data = request.json
    df = create_feature_df(data)
    pred = model_category.predict(df)[0]
    categories = {0: 'Underperformer', 1: 'Steady', 2: 'High Potential'}
    return jsonify({'category': categories.get(pred, 'Unknown')})

@app.route('/predict_bonus', methods=['POST'])
def predict_bonus():
    data = request.json
    base   = float(data.get('BaseSalary', 50000))
    rating = float(data.get('PerformanceRating', 3))
    projects = float(data.get('ProjectsCompleted', 5))
    years  = float(data.get('YearsAtCompany', 2))

    rate = 5.0
    rate += max(0, (rating - 2)) * 3.0
    rate += (projects // 5) * 1.0
    rate += min(5, years) * 0.5
    rate  = min(25, rate)

    bonus = round((base * rate) / 100, 2)
    return jsonify({'predicted_bonus': bonus, 'bonus_rate': round(rate, 2)})

@app.route('/predict_deduction', methods=['POST'])
def predict_deduction():
    data = request.json
    base = float(data.get('BaseSalary', 50000))

    pf = min(1800, round(base * 0.12, 2))

    if   base <= 10000: pt = 0
    elif base <= 15000: pt = 150
    elif base <= 25000: pt = 200
    else:               pt = 300

    annual = base * 12
    if   annual <= 250000:  tax_annual = 0
    elif annual <= 500000:  tax_annual = (annual - 250000) * 0.05
    elif annual <= 1000000: tax_annual = 12500 + (annual - 500000) * 0.20
    else:                   tax_annual = 112500 + (annual - 1000000) * 0.30
    tax = round(tax_annual / 12, 2)

    total = round(pf + pt + tax, 2)
    return jsonify({
        'predicted_deduction': total,
        'breakdown': {'pf': pf, 'professional_tax': pt, 'income_tax': tax}
    })

@app.route('/analyze_productivity', methods=['POST'])
def analyze_productivity():
    data = request.json
    perf_rating = float(data.get('PerformanceRating', 3))
    projects = float(data.get('ProjectsCompleted', 0))
    hours = float(data.get('HoursWorkedPerWeek', 40))
    
    # Simple score model
    # Weights: Performance (50%), Projects Speed (30%), Optimal hours (20%)
    score = (perf_rating / 5) * 50
    proj_score = min(30, (projects / 10) * 30) # Assuming 10 projects is max for score
    
    # Optimal hours 40. High variance decreases score.
    hours_penalty = abs(40 - hours) * 0.5
    hours_score = max(0, 20 - hours_penalty)
    
    total_score = round(score + proj_score + hours_score, 2)
    return jsonify({'productivity_score': total_score})

if __name__ == '__main__':
    app.run(debug=True, port=5000)


