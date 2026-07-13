import re

test_cases = [
    "71.5212",
    "071.5212",
    "671.5212",
    "R 071.5212",
    "Ref 71.5212",
    "005.13",
    "5.13",
    "621.3815 BAE p",
    "R 510",
    "510 KRE a",
    "070-079",
    "70-79",
    "0000.82 UI",
    "0015.677.03",
]

pat = r'\b(\d{1,3})(?:\.?(\d+))?'

for c in test_cases:
    m = re.search(pat, c)
    if m:
        main = int(m.group(1))
        sub = m.group(2) or ""
        print(f"{c:<25} -> main: {main:<5} | sub: {sub}")
    else:
        print(f"{c:<25} -> No Match")
