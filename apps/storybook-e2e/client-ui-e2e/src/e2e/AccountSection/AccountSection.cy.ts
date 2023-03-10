describe('client-ui: AccountSection component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=accountsection--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to AccountSection!');
  });
});
